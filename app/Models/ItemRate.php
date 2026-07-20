<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\BaseModel;

/**
 * Item rate model for Version 1 rental item pricing.
 */
final class ItemRate extends BaseModel
{
    public static function tableName(): string
    {
        return 'item_rates';
    }
}
