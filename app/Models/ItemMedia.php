<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\BaseModel;

/**
 * Relation between a rental item and a media asset.
 */
final class ItemMedia extends BaseModel
{
    public static function tableName(): string
    {
        return 'item_media';
    }
}
