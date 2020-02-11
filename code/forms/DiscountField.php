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
    /**
     * @var DiscountForm
     */
    protected $form;

    /**
     * Validate the discount if it is set by...
     * Checking if it exists
     * Checking if it has uses left
     * Checking if it has a valid date
     * Checking if the event is valid
     * Checking if the discount is valid on one of the registered members
     * TODO: move all these checks to the discount itself? make a method that returns a error message
     *
     * @param \Validator $validator
     *
     * @return bool
     *
     * @throws \ValidationException
     */
    public function validate($validator)
    {
        // If no discount is set continue doing default validation
        if (!isset($this->value) || empty($this->value)) {
            return parent::validate($validator);
        }

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
        if (!$discount->validateUses()) {
            $validator->validationError($this->name, _t(
                'DiscountField.VALIDATION_USED_CHECK',
                'The entered coupon is already used'
            ), 'validation');

            return false;
        }

        // Check if the coupon is expired
        if (!$discount->validateDate()) {
            $validator->validationError($this->name, _t(
                'DiscountField.VALIDATION_DATE_CHECK',
                'The coupon is expired'
            ), 'validation');

            return false;
        }

        // Check if the coupon is allowed on this event
        if (!$discount->validateEvents($this->form->getReservation()->Event())) {
            $validator->validationError($this->name, _t(
                'DiscountField.VALIDATION_EVENT_CHECK',
                'The coupon is not allowed on this event'
            ), 'validation');

            return false;
        }

        // If groups are required check if one of the attendees is in the required group
        if (!$checkMember = $discount->validateGroups()) {
            foreach ($this->form->getReservation()->Attendees() as $attendee) {
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

        $discount->write();
        $this->form->getReservation()->PriceModifiers()->add($discount);
        $this->form->getReservation()->calculateTotal();
        $this->form->getReservation()->write();
        return true;
    }
}
