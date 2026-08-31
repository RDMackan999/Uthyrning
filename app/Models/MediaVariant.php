<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\BaseModel;

/**
 * Generated image variant metadata.
 */
final class MediaVariant extends BaseModel
{
    public static function tableName(): string
    {
        return 'media_variants';
    }
}
