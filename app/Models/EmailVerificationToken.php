<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\BaseModel;

/**
 * Email verification token model storing only token hashes.
 */
final class EmailVerificationToken extends BaseModel
{
    public static function tableName(): string
    {
        return 'email_verification_tokens';
    }
}
