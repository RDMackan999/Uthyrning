<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\BaseModel;

/**
 * Password reset token model storing only token hashes.
 */
final class PasswordResetToken extends BaseModel
{
    public static function tableName(): string
    {
        return 'password_reset_tokens';
    }
}
