<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Facade for the lazy PDO database connection.
 */
final class Database
{
    private static ?DatabaseConnection $connection = null;

    /**
     * Return the shared lazy database connection wrapper.
     */
    public static function connection(): DatabaseConnection
    {
        if (self::$connection === null) {
            self::$connection = new DatabaseConnection(self::defaultLogger());
        }

        return self::$connection;
    }

    /**
     * Return the underlying PDO instance, creating it only when requested.
     */
    public static function pdo(): PDO
    {
        return self::connection()->pdo();
    }

    /**
     * Create a logger without requiring Bootstrap to connect to the database.
     */
    private static function defaultLogger(): Logger
    {
        return new Logger(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs');
    }
}
