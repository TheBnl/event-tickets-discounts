<?php
/**
 * Discount.php
 *
 * @author Bram de Leeuw
 * Date: 30/03/17
 */

namespace Broarm\EventTickets\Discounts\Model;

use Broarm\EventTickets\Model\PriceModifier;
use Broarm\EventTickets\Model\Reservation;
use Broarm\EventTickets\Model\Ticket;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Forms\DateField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Security\Group;
use SilverStripe\ORM\ManyManyList;
use SilverStripe\Security\Member;
use SilverStripe\Forms\NumericField;
use SilverStripe\TagField\TagField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\FieldType\DBDatetime;

/**
 * Class Discount
 *
 * @property string Code
 * @property string ValidFrom
 * @property string ValidTill
 * @property float  Amount
 * @property int    Uses
 * @property bool   AppliesTo
 * @property string DiscountType
 * @method ManyManyList Groups()
 * @method ManyManyList TicketPages()
 * @method ManyManyList Reservations()
 */
class Discount extends PriceModifier
{
    const PRICE = 'PRICE';
    const PERCENTAGE = 'PERCENTAGE';
    const APPLIES_EACH_TICKET = 'EACH_TICKET';

    private static $table_name = 'EventTickets_Discount';

    private static $db = [
        'Code' => 'Varchar(255)',
        'DiscountType' => 'Enum("PRICE,PERCENTAGE","PRICE")',
        'Amount' => 'Decimal',
        'AppliesTo' => 'Enum("CART,EACH_TICKET","CART")',
        'Uses' => 'Int',
        'ValidFrom' => 'Datetime',
        'ValidTill' => 'Datetime',
        'Description' => 'Text',
    ];

    private static $default_sort = 'ValidFrom DESC';

    private static $many_many = [
        'Groups' => Group::class,
        'TicketPages' => SiteTree::class
    ];

    private static $indexes = [
        'Code' => 'unique("Code")'
    ];

    private static $summary_fields = [
        'Code' => 'Code',
        'Description' => 'Description',
        'ValidFrom.Nice' => 'Valid from',
        'ValidTill.Nice' => 'Valid till',
        'Reservations.Count' => 'Uses'
    ];

    private static $defaults = [
        'Uses' => 1
    ];

    /**
     * Create the needed cms fields
     *
     * @return \FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $types = $this->dbObject('DiscountType')->enumValues();
        $appliesTo = $this->dbObject('AppliesTo')->enumValues();

        $ticketPageIds = Ticket::get()->column('TicketPageID');
        $ticketPages = [];
        if (!empty($ticketPageIds)) {
            $ticketPages = SiteTree::get()->filter(['ID' => $ticketPageIds]);
        }

        $fields->addFieldsToTab('Root.Main', [
            TextField::create('Code', 'Code')
                ->setDescription(_t(__CLASS__ . '.CodeHelp', 'The code is generated after saving')),
            TextareaField::create('Description', _t(__CLASS__ . '.Description', 'Description'))
                ->setDescription(_t(__CLASS__ . '.DescriptionHelp', 'The description is only visible in the cms')),
            DropdownField::create('DiscountType', _t(__CLASS__ . '.Type', 'Type of discount'), $types),
            DropdownField::create('AppliesTo', _t(__CLASS__ . '.AppliesTo', 'Discount applies to'), $appliesTo),
            NumericField::create('Amount', _t(__CLASS__ . '.Amount', 'Amount'))->setScale(2),
            
        ]);

        $fields->addFieldsToTab('Root.Constraints', [
            NumericField::create('Uses', _t(__CLASS__ . '.Uses', 'Maximum number of uses')),
            DateField::create('ValidFrom', _t(__CLASS__ . '.ValidForm', 'Valid from')),
            DateField::create('ValidTill', _t(__CLASS__ . '.ValidTill', 'Valid till')),
            TagField::create('Groups', _t(__CLASS__ . '.Groups', 'Constrain to groups'), Group::get())
                ->setShouldLazyLoad(true),
            TagField::create('TicketPages', _t(__CLASS__ . '.TicketPages', 'Constrain to events'), $ticketPages)
                ->setShouldLazyLoad(true)
        ]);

        return $fields;
    }

    public function onBeforeWrite()
    {
        // Generate or validate the set code
        if (empty($this->Code)) {
            $this->Code = $this->generateCode();
        }

        if (empty($this->Title)) {
            // Set the title
            $this->Title = $this->Code;
        }

        parent::onBeforeWrite();
    }

    /**
     * Return the table title
     *
     * @return string
     */
    public function getTableTitle()
    {
        return _t(__CLASS__ . '.Discount', 'Discount');
    }

    /**
     * Check if the discount exceeded the maximum uses
     *
     * @return bool
     */
    public function validateUses()
    {
        return $this->Reservations()->count() <= $this->Uses;
    }

    /**
     * Calculate the discount
     *
     * @param float $total
     * @param Reservation $reservation
     */
    public function updateTotal(&$total, Reservation $reservation)
    {
        switch ($this->DiscountType) {
            case self::PERCENTAGE:
                $discount = ($total / 100 * $this->Amount);
                $total -= $discount;
                break;
            default:
                // case price
                // A Percentage always get's calculated over all tickets
                $discount = $this->AppliesTo === self::APPLIES_EACH_TICKET
                    ? $this->Amount * $reservation->Attendees()->count()
                    : $this->Amount;
                $total -= $discount;
                $total = $total > 0 ? $total : 0;
                break;
        }

        // save the modification on the join
        $this->setPriceModification($discount);
    }

    /**
     * Check if the from and till dates are in the past and future
     *
     * @return bool
     */
    public function validateDate()
    {
        $valid = true;
        if (!empty($this->ValidFrom)) {
            $from = $this->dbObject('ValidFrom');
            $valid = $from->InPast();
        }

        if (!empty($this->ValidTill)) {
            $till = $this->dbObject('ValidTill');
            $valid = $till->InFuture();
        }

        return $valid;
    }

    /**
     * Validate the given member with the allowed groups
     *
     * @param Member $member
     *
     * @return bool
     */
    public function validateGroups(Member $member = null)
    {
        // If groups are attached to the discount, check if valid
        if ($this->Groups()->exists()) {
            if (empty($member)) {
                return false;
            } else {
                $validGroups = $this->Groups()->column('ID');
                $groupMembers = Member::get()->filter('Groups.ID:ExactMatchMulti', $validGroups);
                return (bool)$groupMembers->find('ID', $member->ID);
            }
        }

        return true;
    }

    /**
     * Validate if the given event is in the group of allowed events
     *
     * @param $event
     *
     * @return bool
     */
    public function validateEvents($event)
    {
        // If events are attached to the discount, check if valid
        if ($this->TicketPages()->exists()) {
            if (empty($event)) {
                return false;
            } else {
                $validEvents = $this->TicketPages()->column('ID');
                return in_array($event->ID, $validEvents);
            }
        }

        return true;
    }

    /**
     * Generate a unique coupon code
     *
     * @return string
     */
    public function generateCode()
    {
        return uniqid($this->ID);
    }
}
