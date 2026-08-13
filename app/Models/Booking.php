<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\BaseModel;

/**
 * Booking header model for rental requests.
 */
final class Booking extends BaseModel
{
    public static function tableName(): string
    {
        return 'bookings';
    }
}
