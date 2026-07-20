<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\BaseModel;

/**
 * Rental item model for physical rentable equipment.
 */
final class RentalItem extends BaseModel
{
    public static function tableName(): string
    {
        return 'rental_items';
    }
}
