<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

/**
 * Creates a PDO connection lazily when database access is explicitly requested.
 */
final class DatabaseConnection
{
    private ?PDO $pdo = null;

    public function __construct(private readonly ?Logger $logger = null)
    {
    }

    /**
     * Return the PDO instance, opening the connection only once.
     */
    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = $this->connect();
        }

        return $this->pdo;
    }

    /**
     * Open a PDO connection using safe defaults and without logging secrets.
     */
    private function connect(): PDO
    {
        $host = (string) Config::get('database.host', '127.0.0.1');
        $port = (int) Config::get('database.port', 3306);
        $database = (string) Config::get('database.database', 'uthyrning_dev');
        $username = (string) Config::get('database.username', 'root');
        $password = (string) Config::get('database.password', '');
        $charset = (string) Config::get('database.charset', 'utf8mb4');

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $host,
            $port,
            $database,
            $charset,
        );

        try {
            return new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
            ]);
        } catch (PDOException $exception) {
            $this->logger?->error('Database connection failed', [
                'exception' => $exception::class,
                'code' => $exception->getCode(),
                'host' => $host,
                'port' => $port,
                'database' => $database,
                'charset' => $charset,
            ]);

            throw $exception;
        }
    }
}
