<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Prevents database-writing tests from running outside an explicit test database.
 */
final class TestEnvironmentGuard
{
    private const REFUSAL_MESSAGE = 'Refusing to run database tests outside explicit test environment.';

    /**
     * @return list<string>
     */
    public static function issues(?string $appEnvironment, ?string $databaseName): array
    {
        $issues = [];
        $environment = strtolower(trim((string) $appEnvironment));
        $database = strtolower(trim((string) $databaseName));

        if ($environment !== 'test') {
            $issues[] = 'APP_ENV must be set to test.';
        }

        if ($database === '') {
            $issues[] = 'DB_DATABASE must point to a dedicated test database.';
        } elseif (self::isUnsafeDatabaseName($database)) {
            $issues[] = 'DB_DATABASE must be a dedicated test database name.';
        }

        return $issues;
    }

    /**
     * Stop the current process before migrations, seeders or fixtures can write data.
     */
    public static function assertSafe(?string $appEnvironment, ?string $databaseName): void
    {
        $issues = self::issues($appEnvironment, $databaseName);

        if ($issues !== []) {
            throw new RuntimeException(self::REFUSAL_MESSAGE . ' ' . implode(' ', $issues));
        }
    }

    private static function isUnsafeDatabaseName(string $database): bool
    {
        $unsafeExactNames = [
            'uthyrning',
            'uthyrning_dev',
            'uthyrning_development',
            'uthyrning_prod',
            'uthyrning_production',
        ];

        if (in_array($database, $unsafeExactNames, true)) {
            return true;
        }

        foreach (['prod', 'production', 'live', 'dev', 'development', 'staging'] as $unsafePart) {
            if (str_contains($database, $unsafePart)) {
                return true;
            }
        }

        return !str_contains($database, 'test');
    }
}
