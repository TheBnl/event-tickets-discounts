<?php
/**
 * Discount.php
 *
 * @author Bram de Leeuw
 * Date: 30/03/17
 */

namespace Broarm\EventTickets;

use CalendarEvent;
use Currency;
use DateField;
use DropdownField;
use Group;
use ManyManyList;
use Member;
use NumericField;
use ReadonlyField;
use SS_Datetime;
use TagField;

/**
 * Class Discount
 *
 * @property string Code
 * @property string ValidFrom
 * @property string ValidTill
 * @property float  Amount
 * @property bool   Used
 * @property string DiscountType
 * @method ManyManyList Groups
 * @method ManyManyList Events
 */
class Discount extends PriceModifier
{
    const PRICE = 'PRICE';
    const PERCENTAGE = 'PERCENTAGE';

    private static $singular_name = 'Discount';

    private static $db = array(
        'Amount' => 'Decimal',
        'DiscountType' => 'Enum("PRICE,PERCENTAGE","PRICE")',
        'Code' => 'Varchar(255)',
        'ValidFrom' => 'SS_Datetime',
        'ValidTill' => 'SS_Datetime',
        'Used' => 'Boolean'
    );

    private static $default_sort = "Used ASC, ValidFrom DESC";

    private static $many_many = array(
        'Groups' => 'Group',
        'Events' => 'CalendarEvent'
    );

    private static $summary_fields = array(
        'Code' => 'Code',
        'ValidFrom.Nice' => 'Valid from',
        'ValidTill.Nice' => 'Valid till',
        'Used.Nice' => 'Used',
    );

    private static $defaults = array(
        'Valid' => 1
    );

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $types = $this->dbObject('DiscountType')->enumValues();

        $fields->addFieldsToTab('Root.Main', array(
            $code = ReadonlyField::create('Code', 'Code'),
            DropdownField::create('DiscountType', _t('Discount.TYPE', 'Type of discount'), $types),
            NumericField::create('Amount', _t('Discount.AMOUNT', 'Amount')),
            $validFrom = DateField::create('ValidFrom', _t('Discount.VALID_FROM', 'Valid from')),
            $validTill = DateField::create('ValidTill', _t('Discount.VALID_TILL', 'Valid till')),
            TagField::create('Groups', _t('Discount.GROUPS', 'Constrain to groups'), Group::get()),
            TagField::create('Events', _t('Discount.EVENTS', 'Constrain to events'), CalendarEvent::get())
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
        if (empty($this->Code)) {
            $this->Code = $this->generateCode();
            $this->Title = $this->Code;
        }

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
     * Return a table value
     *
     * @return string
     */
    public function getTableValue()
    {
        switch ($this->DiscountType) {
            case self::PERCENTAGE:
                return "% $this->Amount";
                break;
            default:
            case self::PRICE:
                $currency = new Currency();
                $currency->setValue($this->Amount);
                return $currency->NiceDecimalPoint();
                break;
        }
    }

    /**
     * Calculate the discount
     *
     * @param $total
     */
    public function updateTotal(&$total)
    {
        switch ($this->DiscountType) {
            case self::PERCENTAGE:
                $total = $total - ($total / 100 * $this->Amount);
                break;
            default:
            case self::PRICE:
            // never go below zero
            $discount = $total - $this->Amount;
            $total = $discount > 0 ? $discount : 0;
                break;
        }
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
                $groupMembers = Member::get()->filter('Groups.ID:ExactMatchMulti', $validGroups)->map('ID', 'Title');

                echo "<pre>";
                print_r($groupMembers);
                echo "</pre>";
                exit();
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
        return uniqid();
    }
}
