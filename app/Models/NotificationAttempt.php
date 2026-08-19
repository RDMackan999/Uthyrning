<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\BaseModel;

/**
 * Append-only delivery attempt model for notifications.
 */
final class NotificationAttempt extends BaseModel
{
    public static function tableName(): string
    {
        return 'notification_attempts';
    }
}
