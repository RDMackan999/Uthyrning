<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\BaseModel;

/**
 * Rental fulfillment header for actual handover and return facts.
 */
final class RentalFulfillment extends BaseModel
{
    public static function tableName(): string
    {
        return 'rental_fulfillments';
    }
}
