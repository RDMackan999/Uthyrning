<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\BaseModel;

/**
 * Media asset metadata for uploaded files.
 */
final class MediaAsset extends BaseModel
{
    public static function tableName(): string
    {
        return 'media_assets';
    }
}
