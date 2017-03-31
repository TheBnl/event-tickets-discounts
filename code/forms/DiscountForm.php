<?php
/**
 * DiscountForm.php
 *
 * @author Bram de Leeuw
 * Date: 10/03/17
 */

namespace Broarm\EventTickets;

use FieldList;
use FormAction;

/**
 * Class DiscountForm
 *
 * @package Broarm\EventTickets
 */
class DiscountForm extends FormStep
{
    /**
     * @var Reservation
     */
    protected $reservation;

    public function __construct($controller, $name, Reservation $reservation)
    {
        $fields = FieldList::create(
            SummaryField::create('Summary', '', $this->reservation = $reservation),
            DiscountField::create('CouponCode', _t('DiscountForm.COUPON_CODE', 'Coupon code'))
        );

        $actions = FieldList::create(
            FormAction::create('calculateDiscount', _t('DiscountForm.CONTINUE', 'Continue'))
        );

        // Update the discount form with extra fields
        $this->extend('updateDiscountForm');

        parent::__construct($controller, $name, $fields, $actions);
    }

    /**
     * Return the reservation
     * TODO: ad this to base Form step ?
     *
     * @return Reservation
     */
    public function getReservation()
    {
        return $this->reservation;
    }

    /**
     * The checking of the given coupon code is handled by the DiscountField valid method
     * @see DiscountField::validate()
     *
     * @return \SS_HTTPResponse
     */
    public function calculateDiscount()
    {
        return $this->nextStep();
    }
}