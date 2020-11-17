<?php

namespace Broarm\EventTickets;

use Extension;

/**
 * Class AddDiscountExtension
 * @package Broarm\EventTickets
 * @property ReservationForm $owner
 */
class AddDiscountExtension extends Extension
{
    public function updateForm()
    {
        $fields = $this->owner->Fields();
        $reservation = $this->owner->getReservation();
        $event = $reservation->Event();

        if (!$event->DisableDiscountField) {
            $fields->add(
                $field = DiscountField::create('CouponCode', _t('DiscountForm.COUPON_CODE', 'Coupon code'))
            );
            $field->setForm($this->owner);
        }
    }
}
