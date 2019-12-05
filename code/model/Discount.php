<?php
/**
 * Discount.php
 *
 * @author Bram de Leeuw
 * Date: 30/03/17
 */

namespace Broarm\EventTickets;

use CalendarEvent;
use DateField;
use DropdownField;
use Group;
use ManyManyList;
use Member;
use NumericField;
use SS_Datetime;
use TagField;
use TextareaField;
use TextField;

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
 * @method ManyManyList Events()
 * @method ManyManyList Reservations()
 */
class Discount extends PriceModifier
{
    const PRICE = 'PRICE';
    const PERCENTAGE = 'PERCENTAGE';
    const APPLIES_EACH_TICKET = 'EACH_TICKET';

    private static $singular_name = 'Discount';

    private static $db = array(
        'Description' => 'Text',
        'Amount' => 'Decimal',
        'Uses' => 'Int',
        'DiscountType' => 'Enum("PRICE,PERCENTAGE","PRICE")',
        'Code' => 'Varchar(255)',
        'ValidFrom' => 'SS_Datetime',
        'ValidTill' => 'SS_Datetime',
        'AppliesTo' => 'Enum("CART,EACH_TICKET","CART")'
    );

    private static $default_sort = "ValidFrom DESC";

    private static $many_many = array(
        'Groups' => 'Group',
        'Events' => 'CalendarEvent'
    );

    private static $indexes = array(
        'Code' => 'unique("Code")'
    );

    private static $summary_fields = array(
        'Code' => 'Code',
        'Description' => 'Description',
        'ValidFrom.Nice' => 'Valid from',
        'ValidTill.Nice' => 'Valid till',
        'Reservations.Count' => 'Uses'
    );

    private static $defaults = array(
        'Uses' => 1
    );

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

        $fields->addFieldsToTab('Root.Main', array(
            $code = TextField::create('Code', 'Code'),
            TextareaField::create('Description', 'Description')->setDescription('The description is only visible in the cms'),
            DropdownField::create('DiscountType', _t('Discount.TYPE', 'Type of discount'), $types),
            DropdownField::create('AppliesTo', _t('Discount.AppliesTo', 'Discount applies to'), $appliesTo),
            NumericField::create('Amount', _t('Discount.AMOUNT', 'Amount')),
            NumericField::create('Uses', _t('Discount.USES', 'Maximum number of uses')),
            $validFrom = DateField::create('ValidFrom', _t('Discount.VALID_FROM', 'Valid from')),
            $validTill = DateField::create('ValidTill', _t('Discount.VALID_TILL', 'Valid till')),
            TagField::create('Groups', _t('Discount.GROUPS', 'Constrain to groups'), Group::get())
                ->setShouldLazyLoad(true),
            TagField::create('Events', _t('Discount.EVENTS', 'Constrain to events'), CalendarEvent::get())
                ->setShouldLazyLoad(true)
        ));

        $code->setDescription(
            _t('Discount.CODE_HELP', 'The code is generated after saving')
        );

        $validFrom
            ->setConfig('showcalendar', true)
            ->setDescription(_t('Discount.VALID_FROM_HELP', 'If no date is set the current date is used'));
        $validTill
            ->setConfig('showcalendar', true)
            ->setDescription(_t('Discount.VALID_TILL_HELP', 'If no date is set the current date + 1 year is used'));

        $fields->removeByName(array('Title'));
        return $fields;
    }

    public function onBeforeWrite()
    {
        // Generate or validate the set code
        if (empty($this->Code)) {
            $this->Code = $this->generateCode();
        } elseif (empty($this->Title) && $codes = self::get()->filter('Code:PartialMatch', $this->Code)) {
            if ($codes->count() >= 1) {
                $this->Code .= "-{$codes->count()}";
            }
        }

        // Set the title
        $this->Title = $this->Code;

        // Set the default dates
        if (empty($this->ValidFrom) && empty($this->ValidTill)) {
            $format = 'Y-m-d';
            $this->ValidFrom = $start = date($format);
            $this->ValidTill = date($format, strtotime("$start + 1 year"));
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
        return _t('Discount.DISCOUNT', 'Discount');
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
        /** @var SS_Datetime $from */
        $from = $this->dbObject('ValidFrom');
        /** @var SS_Datetime $till */
        $till = $this->dbObject('ValidTill');

        return (bool)($from->InPast() && $till->InFuture());
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
     * @param CalendarEvent $event
     *
     * @return bool
     */
    public function validateEvents(CalendarEvent $event)
    {
        // If events are attached to the discount, check if valid
        if ($this->Events()->exists()) {
            if (empty($event)) {
                return false;
            } else {
                $validEvents = $this->Events()->column('ID');
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
