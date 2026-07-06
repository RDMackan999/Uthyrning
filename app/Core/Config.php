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
     * Load application configuration from config.php or config.example.php.
     */
    public static function load(string $basePath): void
    {
        $configPath = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
        $examplePath = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.example.php';
        $path = is_file($configPath) ? $configPath : $examplePath;

        if (!is_file($path)) {
            self::$items = [];
            return;
        }

        $config = require $path;
        self::$items = is_array($config) ? $config : [];
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
}
