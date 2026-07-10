<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\BaseModel;

/**
 * Organization model for the future owner/marketplace tenant boundary.
 */
final class Organization extends BaseModel
{
    public static function tableName(): string
    {
        return 'organizations';
    }
}
