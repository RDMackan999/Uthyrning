<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\BaseModel;

/**
 * Login attempt model for future rate limiting and audit support.
 */
final class LoginAttempt extends BaseModel
{
    public static function tableName(): string
    {
        return 'login_attempts';
    }
}
