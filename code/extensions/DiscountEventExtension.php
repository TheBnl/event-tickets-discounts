<?php

namespace Broarm\EventTickets;

use CheckboxField;
use DataExtension;
use FieldList;

/**
 * Class DiscountEventExtension
 * @package Broarm\EventTickets
 * @property \CalendarEvent $owner
 */
class DiscountEventExtension extends DataExtension
{
    private static $db = [
        'DisableDiscountField' => 'Boolean'
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $fields->addFieldsToTab('Root.Tickets', [
            CheckboxField::create('DisableDiscountField', _t(__CLASS__ . '.DisableDiscountField', 'Disable discount field'))
        ]);
    }
}
