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
}
