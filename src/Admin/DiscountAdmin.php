<?php

namespace Broarm\EventTickets\Discounts\Admin;

use Broarm\EventTickets\Discounts\Model\Discount;
use SilverStripe\Admin\ModelAdmin;

class DiscountAdmin extends ModelAdmin
{
    private static $managed_models = [
        Discount::class
    ];

    private static $url_segment = 'discounts';

    private static $menu_title = 'Ticket Discounts';

    private static $menu_icon_class = 'font-icon-credit-card';
}
