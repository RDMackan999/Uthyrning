<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Fails closed when production starts with unsafe release configuration.
 */
final class ProductionEnvironmentGuard
{
    private const REFUSAL_MESSAGE = 'Production configuration is incomplete. Review deployment settings before starting.';

    /**
     * Stop production runtime before routing if required safeguards are missing.
     */
    public static function assertRuntimeReady(string $basePath, ?Logger $logger = null): void
    {
        if (self::environment() !== 'production') {
            return;
        }

        $issues = self::issues($basePath);

        if ($issues === []) {
            return;
        }

        $logger?->critical('Production runtime refused to start', [
            'issue_codes' => $issues,
        ]);

        throw new RuntimeException(self::REFUSAL_MESSAGE);
    }

    /**
     * Return safe issue codes without config values or secrets.
     *
     * @return list<string>
     */
    public static function issues(string $basePath): array
    {
        if (self::environment() !== 'production') {
            return [];
        }

        $issues = [];

        if (Config::get('app.debug', false) !== false) {
            $issues[] = 'app_debug_must_be_false';
        }

        if (!self::isHttpsUrl(Config::get('app.base_url'))) {
            $issues[] = 'app_base_url_must_be_https';
        }

        if (Config::get('security.force_https', false) !== true) {
            $issues[] = 'security_force_https_must_be_true';
        }

        if (Config::get('auth.session_cookie_secure', false) !== true) {
            $issues[] = 'session_cookie_secure_must_be_true';
        }

        if (Config::get('auth.csrf_cookie_secure', false) !== true) {
            $issues[] = 'csrf_cookie_secure_must_be_true';
        }

        if (self::databaseLooksUnsafe()) {
            $issues[] = 'database_config_must_be_production_safe';
        }

        if (self::mailLooksUnsafe()) {
            $issues[] = 'smtp_transport_must_be_configured';
        }

        foreach (self::requiredWritableDirectories($basePath) as $code => $directory) {
            if (!self::ensureWritableDirectory($directory)) {
                $issues[] = $code . '_must_be_writable';
            }
        }

        return $issues;
    }

    private static function environment(): string
    {
        return strtolower(trim((string) Config::get('app.environment', 'production'))) ?: 'production';
    }

    private static function isHttpsUrl(mixed $url): bool
    {
        return is_string($url)
            && str_starts_with(strtolower(trim($url)), 'https://')
            && !str_contains(strtolower($url), 'localhost');
    }

    private static function databaseLooksUnsafe(): bool
    {
        $database = strtolower(trim((string) Config::get('database.database', '')));
        $username = trim((string) Config::get('database.username', ''));
        $password = (string) Config::get('database.password', '');

        if ($database === '' || $username === '' || $password === '') {
            return true;
        }

        foreach (['test', 'dev', 'development', 'staging', 'example'] as $unsafePart) {
            if (str_contains($database, $unsafePart)) {
                return true;
            }
        }

        return false;
    }

    private static function mailLooksUnsafe(): bool
    {
        if (strtolower(trim((string) Config::get('notifications.email_transport', 'development'))) !== 'smtp') {
            return true;
        }

        $host = strtolower(trim((string) Config::get('notifications.smtp.host', '')));
        $fromAddress = strtolower(trim((string) Config::get('notifications.smtp.from_address', '')));
        $encryption = strtolower(trim((string) Config::get('notifications.smtp.encryption', '')));

        return $host === ''
            || $host === 'smtp.example.com'
            || $fromAddress === ''
            || str_ends_with($fromAddress, '@example.com')
            || !in_array($encryption, ['tls', 'starttls', 'ssl', 'smtps'], true);
    }

    /**
     * @return array<string, string>
     */
    private static function requiredWritableDirectories(string $basePath): array
    {
        $storagePath = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'storage';
        $mediaRoot = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim((string) Config::get('media.local_root', 'storage/media')));

        if ($mediaRoot === '' || preg_match('/^[A-Za-z]:\\\\|^\//', $mediaRoot) === 1) {
            return [
                'storage_logs' => $storagePath . DIRECTORY_SEPARATOR . 'logs',
                'storage_sessions' => $storagePath . DIRECTORY_SEPARATOR . 'sessions',
                'storage_temp' => $storagePath . DIRECTORY_SEPARATOR . 'temp',
            ];
        }

        $mediaPath = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . trim($mediaRoot, DIRECTORY_SEPARATOR);

        return [
            'storage_logs' => $storagePath . DIRECTORY_SEPARATOR . 'logs',
            'storage_sessions' => $storagePath . DIRECTORY_SEPARATOR . 'sessions',
            'storage_temp' => $storagePath . DIRECTORY_SEPARATOR . 'temp',
            'media_original' => $mediaPath . DIRECTORY_SEPARATOR . 'original',
            'media_variants' => $mediaPath . DIRECTORY_SEPARATOR . 'variants',
        ];
    }

    private static function ensureWritableDirectory(string $directory): bool
    {
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return false;
        }

        return is_writable($directory);
    }
}
