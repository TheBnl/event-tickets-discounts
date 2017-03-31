<?php
/**
 * DiscountStep.php
 *
 * @author Bram de Leeuw
 * Date: 29/03/17
 */


namespace Broarm\EventTickets;

use CalendarEvent_Controller;
use Extension;

/**
 * Class DiscountStep
 * 
 * @property TicketControllerExtension|TicketExtension|CalendarEvent_Controller $owner
 */
class DiscountStep extends Extension
{
    private static $allowed_actions = array(
        'discount'
    );

    /**
     * Continue to the summary step
     *
     * @return DiscountController
     */
    public function discount()
    {
        return new DiscountController($this->owner->dataRecord);
    }
}
