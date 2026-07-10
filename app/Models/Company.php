<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\BaseModel;

/**
 * Company model for business entities connected to organizations and users.
 */
final class Company extends BaseModel
{
    public static function tableName(): string
    {
        return 'companies';
    }
}
