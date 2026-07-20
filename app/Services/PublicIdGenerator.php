<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Generates public, non-sequential identifiers for entities that need public URLs.
 */
final class PublicIdGenerator
{
    /**
     * Generate a stable public id with a short domain prefix.
     */
    public function generate(string $prefix = 'itm'): string
    {
        return strtolower(trim($prefix)) . '_' . bin2hex(random_bytes(8));
    }
}
