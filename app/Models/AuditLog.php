<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\BaseModel;

/**
 * Audit log model for append-only security and business events.
 */
final class AuditLog extends BaseModel
{
    public static function tableName(): string
    {
        return 'audit_logs';
    }
}
