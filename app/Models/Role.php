<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\BaseModel;

/**
 * Role model for system and future organization-scoped roles.
 */
final class Role extends BaseModel
{
    public static function tableName(): string
    {
        return 'roles';
    }
}
