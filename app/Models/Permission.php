<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\BaseModel;

/**
 * Permission model for named capabilities used by roles.
 */
final class Permission extends BaseModel
{
    public static function tableName(): string
    {
        return 'permissions';
    }
}
