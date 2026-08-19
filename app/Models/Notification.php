<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\BaseModel;

/**
 * Notification model for booking-related outbound messages.
 */
final class Notification extends BaseModel
{
    public static function tableName(): string
    {
        return 'notifications';
    }
}
