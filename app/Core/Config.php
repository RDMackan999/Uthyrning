<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Loads PHP configuration arrays and exposes values through dot notation.
 */
final class Config
{
    /**
     * @var array<string, mixed>
     */
    private static array $items = [];

    /**
     * Load application and database configuration from local files or examples.
     */
    public static function load(string $basePath): void
    {
        self::$items = array_replace_recursive(
            self::loadFile($basePath, 'config'),
            self::loadFile($basePath, 'database'),
        );

        self::applyEnvironmentOverrides();
    }

    /**
     * Read a config value with dot notation, for example app.name.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$items;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Return all loaded configuration values.
     *
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return self::$items;
    }

    /**
     * Load one config file, preferring the local file over the example file.
     *
     * @return array<string, mixed>
     */
    private static function loadFile(string $basePath, string $name): array
    {
        $configDirectory = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'config';
        $configPath = $configDirectory . DIRECTORY_SEPARATOR . $name . '.php';
        $examplePath = $configDirectory . DIRECTORY_SEPARATOR . $name . '.example.php';
        $path = is_file($configPath) ? $configPath : $examplePath;

        if (!is_file($path)) {
            return [];
        }

        $config = require $path;

        return is_array($config) ? $config : [];
    }

    /**
     * Allow local runtime overrides without committing real config files.
     */
    private static function applyEnvironmentOverrides(): void
    {
        $overrides = [
            'APP_ENV' => 'app.environment',
            'APP_DEBUG' => 'app.debug',
            'APP_TIMEZONE' => 'app.timezone',
            'APP_BASE_URL' => 'app.base_url',
            'AUTH_SESSION_COOKIE_SECURE' => 'auth.session_cookie_secure',
            'AUTH_CSRF_COOKIE_SECURE' => 'auth.csrf_cookie_secure',
            'SECURITY_FORCE_HTTPS' => 'security.force_https',
            'SECURITY_TRUSTED_PROXIES' => 'security.trusted_proxies',
            'MAIL_TRANSPORT' => 'notifications.email_transport',
            'SMTP_HOST' => 'notifications.smtp.host',
            'SMTP_PORT' => 'notifications.smtp.port',
            'SMTP_ENCRYPTION' => 'notifications.smtp.encryption',
            'SMTP_USERNAME' => 'notifications.smtp.username',
            'SMTP_PASSWORD' => 'notifications.smtp.password',
            'SMTP_FROM_ADDRESS' => 'notifications.smtp.from_address',
            'SMTP_FROM_NAME' => 'notifications.smtp.from_name',
            'MEDIA_LOCAL_ROOT' => 'media.local_root',
            'DB_HOST' => 'database.host',
            'DB_PORT' => 'database.port',
            'DB_DATABASE' => 'database.database',
            'DB_USERNAME' => 'database.username',
            'DB_PASSWORD' => 'database.password',
            'DB_CHARSET' => 'database.charset',
        ];

        foreach ($overrides as $environmentKey => $configKey) {
            $value = getenv($environmentKey);

            if ($value === false) {
                continue;
            }

            self::set($configKey, self::normalizeEnvironmentValue($environmentKey, $value));
        }
    }

    /**
     * Set a config value with dot notation.
     */
    private static function set(string $key, mixed $value): void
    {
        $items = &self::$items;

        foreach (explode('.', $key) as $segment) {
            if (!isset($items[$segment]) || !is_array($items[$segment])) {
                $items[$segment] = [];
            }

            $items = &$items[$segment];
        }

        $items = $value;
    }

    /**
     * Keep environment values typed where the config already expects it.
     */
    private static function normalizeEnvironmentValue(string $key, string $value): mixed
    {
        return match ($key) {
            'APP_DEBUG',
            'AUTH_SESSION_COOKIE_SECURE',
            'AUTH_CSRF_COOKIE_SECURE',
            'SECURITY_FORCE_HTTPS' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'DB_PORT',
            'SMTP_PORT' => (int) $value,
            'SECURITY_TRUSTED_PROXIES' => array_values(array_filter(
                array_map('trim', explode(',', $value)),
                static fn (string $proxy): bool => $proxy !== ''
            )),
            default => $value,
        };
    }
}
