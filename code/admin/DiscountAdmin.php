<?php

namespace Broarm\EventTickets;

use ModelAdmin;

/**
 * Class DiscountAdmin
 *
 * @property array   managed_models  An array of classnames to manage.
 * @property string  url_segment     The url section of this admin section.
 * @property string  menu_title      The menu title for this admin section.
 * @property string  menu_icon       The menu icon for this admin section.
 *
 * @author   bramdeleeuw
 * @package  nieuwspoort
 */
class DiscountAdmin extends ModelAdmin
{
    private static $managed_models = array('Broarm\EventTickets\Discount');

    private static $url_segment = 'discounts';

    private static $menu_title = 'Ticket Discounts';

    private static $menu_icon = '/event-tickets_discounts/images/discount.png';
}
