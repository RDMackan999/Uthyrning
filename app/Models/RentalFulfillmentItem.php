<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\BaseModel;

/**
 * Immutable item-level condition snapshot for fulfillment.
 */
final class RentalFulfillmentItem extends BaseModel
{
    public static function tableName(): string
    {
        return 'rental_fulfillment_items';
    }
}
