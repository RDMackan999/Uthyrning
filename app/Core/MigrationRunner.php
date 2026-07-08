<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Runs SQL migration files from database/migrations in filename order.
 */
final class MigrationRunner
{
    private readonly string $migrationsPath;

    public function __construct(
        private readonly string $basePath,
        private readonly ?Logger $logger = null,
    ) {
        $this->migrationsPath = rtrim($basePath, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'database'
            . DIRECTORY_SEPARATOR . 'migrations';
    }

    /**
     * Run all pending migrations and return the filenames that were executed.
     *
     * @param null|callable(Migration): void $onRun
     * @return list<string>
     */
    public function run(?callable $onRun = null): array
    {
        try {
            $executedMigrations = $this->executedMigrations();
            $batch = $this->nextBatch();
            $ran = [];

            foreach ($this->migrationFiles() as $migration) {
                if (in_array($migration->filename(), $executedMigrations, true)) {
                    continue;
                }

                if ($onRun !== null) {
                    $onRun($migration);
                }

                $this->pdo()->exec($migration->sql());
                $this->recordMigration($migration, $batch);
                $ran[] = $migration->filename();
            }

            return $ran;
        } catch (Throwable $exception) {
            $this->logger?->error('Migration runner failed', [
                'exception' => $exception::class,
                'code' => $exception->getCode(),
            ]);

            throw $exception;
        }
    }

    /**
     * @return list<Migration>
     */
    private function migrationFiles(): array
    {
        $directory = realpath($this->migrationsPath);

        if ($directory === false || !is_dir($directory)) {
            throw new RuntimeException('Migrations directory does not exist.');
        }

        $paths = glob($directory . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        sort($paths, SORT_STRING);

        $migrations = [];

        foreach ($paths as $path) {
            $realPath = realpath($path);

            if ($realPath === false || !is_file($realPath)) {
                continue;
            }

            if (!str_starts_with($realPath, $directory . DIRECTORY_SEPARATOR)) {
                throw new RuntimeException('Migration path escaped the migrations directory.');
            }

            $migrations[] = new Migration(basename($realPath), $realPath);
        }

        return $migrations;
    }

    /**
     * @return list<string>
     */
    private function executedMigrations(): array
    {
        if (!$this->migrationsTableExists()) {
            return [];
        }

        $statement = $this->pdo()->query('SELECT migration FROM migrations ORDER BY migration ASC');

        if ($statement === false) {
            return [];
        }

        /** @var list<string> $migrations */
        $migrations = $statement->fetchAll(PDO::FETCH_COLUMN);

        return $migrations;
    }

    private function nextBatch(): int
    {
        if (!$this->migrationsTableExists()) {
            return 1;
        }

        $statement = $this->pdo()->query('SELECT MAX(batch) FROM migrations');
        $currentBatch = $statement === false ? 0 : (int) $statement->fetchColumn();

        return $currentBatch + 1;
    }

    private function migrationsTableExists(): bool
    {
        try {
            $statement = $this->pdo()->query("SHOW TABLES LIKE 'migrations'");

            return $statement !== false && $statement->fetchColumn() !== false;
        } catch (PDOException $exception) {
            $this->logger?->error('Could not inspect migrations table', [
                'exception' => $exception::class,
                'code' => $exception->getCode(),
            ]);

            throw $exception;
        }
    }

    private function recordMigration(Migration $migration, int $batch): void
    {
        $statement = $this->pdo()->prepare(
            'INSERT INTO migrations (migration, batch, executed_at) VALUES (:migration, :batch, UTC_TIMESTAMP())',
        );

        $statement->execute([
            'migration' => $migration->filename(),
            'batch' => $batch,
        ]);
    }

    private function pdo(): PDO
    {
        return Database::pdo();
    }
}
