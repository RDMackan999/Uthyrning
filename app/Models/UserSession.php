<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\BaseModel;

/**
 * User session model for future server-side session tracking.
 */
final class UserSession extends BaseModel
{
    public static function tableName(): string
    {
        return 'user_sessions';
    }
}
