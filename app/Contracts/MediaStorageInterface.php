<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Storage adapter contract for media files.
 */
interface MediaStorageInterface
{
    /**
     * Store a local source file under a server-generated storage key.
     */
    public function store(string $sourcePath, string $storageKey): void;

    /**
     * Read file bytes for delivery.
     */
    public function read(string $storageKey): string;

    /**
     * Check whether a file exists.
     */
    public function exists(string $storageKey): bool;

    /**
     * Delete a newly created physical file during rollback/cleanup.
     */
    public function delete(string $storageKey): void;
}
