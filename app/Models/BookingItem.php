<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\BaseModel;

/**
 * Booking item model for rented equipment rows.
 */
final class BookingItem extends BaseModel
{
    public static function tableName(): string
    {
        return 'booking_items';
    }
}
