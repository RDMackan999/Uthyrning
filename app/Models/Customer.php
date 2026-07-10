<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\BaseModel;

/**
 * Customer model for private and company customer records.
 */
final class Customer extends BaseModel
{
    public static function tableName(): string
    {
        return 'customers';
    }
}
