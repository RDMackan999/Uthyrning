<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\BaseModel;

/**
 * Manual availability block for one rental item.
 */
final class ItemAvailabilityBlock extends BaseModel
{
    public static function tableName(): string
    {
        return 'blocked_periods';
    }
}
