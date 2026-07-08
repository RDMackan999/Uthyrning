<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Represents one SQL migration file selected by the migration runner.
 */
final class Migration
{
    public function __construct(
        private readonly string $filename,
        private readonly string $path,
    ) {
    }

    /**
     * Return the migration filename, for example 0001_create_migrations_table.sql.
     */
    public function filename(): string
    {
        return $this->filename;
    }

    /**
     * Return the resolved absolute migration path.
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * Read the migration SQL from disk.
     */
    public function sql(): string
    {
        $sql = file_get_contents($this->path);

        if ($sql === false || trim($sql) === '') {
            throw new RuntimeException(sprintf('Migration file is empty or unreadable: %s', $this->filename));
        }

        return $sql;
    }
}
