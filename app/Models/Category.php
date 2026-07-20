<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\BaseModel;

/**
 * Category model for rental item categorization.
 */
final class Category extends BaseModel
{
    public static function tableName(): string
    {
        return 'item_categories';
    }
}
