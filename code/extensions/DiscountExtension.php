<?php
/**
 * DiscountExtension.php
 *
 * @author Bram de Leeuw
 * Date: 31/03/17
 */

namespace Broarm\EventTickets;

use DataExtension;

/**
 * Class DiscountExtension
 *
 * @property DiscountExtension|Reservation $owner
 * @method Discount Discount
 */
class DiscountExtension extends DataExtension
{
    /**
     * @param int $total
     */
    public function updateTotal(&$total) {
        // If a valid discount has been added to the reservation calculate
        $total = $total - 5;

        if ($this->owner->Discount()->exists()) {
            // 10 is the discount ...
            //$total = $total;// - 10;
        }
    }
}
