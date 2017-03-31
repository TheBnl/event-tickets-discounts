<?php
/**
 * DiscountController.php
 *
 * @author Bram de Leeuw
 * Date: 29/03/17
 */

namespace Broarm\EventTickets;

/**
 * Class DiscountController
 *
 * @package Broarm\EventTickets
 */
class DiscountController extends CheckoutStepController
{
    protected $step = 'discount';

    private static $allowed_actions = array(
        'DiscountForm'
    );

    /**
     * Get the discount form
     *
     * @return DiscountForm
     */
    public function DiscountForm()
    {
        $discountForm = new DiscountForm($this, 'DiscountForm', ReservationSession::get());
        $discountForm->setNextStep(CheckoutSteps::nextStep($this->step));
        return $discountForm;
    }
}
