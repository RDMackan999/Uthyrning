<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\BaseModel;

/**
 * User model for identity records without authentication behavior.
 */
final class User extends BaseModel
{
    public static function tableName(): string
    {
        return 'users';
    }
}
