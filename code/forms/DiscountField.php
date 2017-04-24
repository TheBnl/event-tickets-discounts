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
use TextField;

/**
 * Class DiscountForm
 *
 * @package Broarm\EventTickets
 */
class DiscountField extends TextField
{
    public function validate($validator)
    {
        if (isset($this->value) && $this->value) {

            /** @var Discount $discount */
            // Check if the discount exists
            if (!$discount = Discount::get()->find('Code', $this->value)) {
                $validator->validationError($this->name, _t(
                    'DiscountField.VALIDATION_NOT_FOUND',
                    'The entered coupon is not found'
                ), 'validation');

                return false;
            }

            // Check if the discount is already used
            if ($discount->getUsed()) {
                $validator->validationError($this->name, _t(
                    'DiscountField.VALIDATION_USED_CHECK',
                    'The entered coupon is already used'
                ), 'validation');

                return false;
            }

            /** @var DiscountForm $form */
            $form = $this->form;

            // Check if the coupon is expired
            if (!$checkDate = $discount->validateDate()) {
                $validator->validationError($this->name, _t(
                    'DiscountField.VALIDATION_DATE_CHECK',
                    'The coupon is expired'
                ), 'validation');

                return false;
            }

            // Check if the coupon is allowed on this event
            if (!$checkEvent = $discount->validateEvents($form->getReservation()->Event())) {
                $validator->validationError($this->name, _t(
                    'DiscountField.VALIDATION_EVENT_CHECK',
                    'The coupon is not allowed on this event'
                ), 'validation');

                return false;
            }

            // If groups are required check if one of the attendees is in the required group
            if (!$checkMember = $discount->validateGroups()) {
                foreach ($form->getReservation()->Attendees() as $attendee) {
                    /** @var Attendee $attendee */
                    if ($attendee->Member()->exists() && $member = $attendee->Member()) {
                        if ($checkMember = $discount->validateGroups($member)) {
                            // If one of the member is part of the group validate the discount
                            break;
                        } else {
                            $checkMember = false;
                        }
                    }
                }
            }

            if (!$checkMember) {
                $validator->validationError($this->name, _t(
                    'DiscountField.VALIDATION_MEMBER_CHECK',
                    'None of the attendees is allowed to use this coupon'
                ), 'validation');

                return false;
            }

            // If all checks passed add the discount and recalculate the price
            if ($checkDate && $checkEvent && $checkMember) {
                //$discount->Used = true;
                $discount->write();
                $form->getReservation()->PriceModifiers()->add($discount);
                $form->getReservation()->calculateTotal();
                $form->getReservation()->write();
                return true;
            }

            return false;
        } else {
            // If the field is empty continue without adding the discount
            return parent::validate($validator);
        }
    }
}