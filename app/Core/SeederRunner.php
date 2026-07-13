<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Runs idempotent SQL seed files from database/seeders in filename order.
 */
final class SeederRunner
{
    private readonly string $seedersPath;

    public function __construct(
        private readonly string $basePath,
        private readonly ?Logger $logger = null
    ) {
        $this->seedersPath = rtrim($basePath, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'database'
            . DIRECTORY_SEPARATOR . 'seeders';
    }

    /**
     * Run all seed files and return the filenames that were executed.
     *
     * @param null|callable(string): void $onRun
     * @return list<string>
     */
    public function run(?callable $onRun = null): array
    {
        $ran = [];

        foreach ($this->seedFiles() as $seedFile) {
            $filename = basename($seedFile);

            if ($onRun !== null) {
                $onRun($filename);
            }

            $this->logger?->info('Running database seeder', [
                'seeder' => $filename,
            ]);

            $this->runSeedFile($seedFile);
            $ran[] = $filename;
        }

        return $ran;
    }

    /**
     * Run one SQL seed file inside its own transaction.
     */
    private function runSeedFile(string $seedFile): void
    {
        $pdo = $this->pdo();
        $sql = (string) file_get_contents($seedFile);

        try {
            $pdo->beginTransaction();
            $pdo->exec($sql);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $this->logger?->error('Database seeder failed', [
                'exception' => $exception::class,
                'code' => $exception->getCode(),
                'seeder' => basename($seedFile),
            ]);

            throw $exception;
        }
    }

    /**
     * Return allowed seed files in deterministic order.
     *
     * @return list<string>
     */
    private function seedFiles(): array
    {
        $directory = realpath($this->seedersPath);

        if ($directory === false || !is_dir($directory)) {
            throw new RuntimeException('Seeders directory does not exist.');
        }

        $paths = glob($directory . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        sort($paths, SORT_STRING);

        $seedFiles = [];

        foreach ($paths as $path) {
            $realPath = realpath($path);

            if ($realPath === false || !is_file($realPath)) {
                continue;
            }

            if (!str_starts_with($realPath, $directory . DIRECTORY_SEPARATOR)) {
                throw new RuntimeException('Seeder path escaped the seeders directory.');
            }

            $seedFiles[] = $realPath;
        }

        return $seedFiles;
    }

    /**
     * Return the shared PDO connection.
     */
    private function pdo(): PDO
    {
        return Database::pdo();
    }
}
