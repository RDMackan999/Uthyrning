<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Contracts\MediaStorageInterface;
use App\Core\Config;
use App\Core\MediaException;

/**
 * Local private filesystem storage for development media files.
 */
final class LocalMediaStorage implements MediaStorageInterface
{
    public function __construct(private readonly ?string $rootPath = null)
    {
    }

    /**
     * Store a local source file under a safe relative storage key.
     */
    public function store(string $sourcePath, string $storageKey): void
    {
        if (!is_file($sourcePath)) {
            throw new MediaException('Media source file is not available.');
        }

        $targetPath = $this->pathForKey($storageKey);
        $directory = dirname($targetPath);

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new MediaException('Media storage directory could not be created.');
        }

        $stored = is_uploaded_file($sourcePath)
            ? move_uploaded_file($sourcePath, $targetPath)
            : copy($sourcePath, $targetPath);

        if (!$stored) {
            throw new MediaException('Media file could not be stored.');
        }
    }

    /**
     * Read bytes from a stored media file.
     */
    public function read(string $storageKey): string
    {
        $path = $this->pathForKey($storageKey);

        if (!is_file($path)) {
            throw new MediaException('Media file is not available.');
        }

        $content = file_get_contents($path);

        if ($content === false) {
            throw new MediaException('Media file could not be read.');
        }

        return $content;
    }

    /**
     * Check whether a stored media file exists.
     */
    public function exists(string $storageKey): bool
    {
        return is_file($this->pathForKey($storageKey));
    }

    /**
     * Delete a newly written file during rollback cleanup.
     */
    public function delete(string $storageKey): void
    {
        $path = $this->pathForKey($storageKey);

        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function pathForKey(string $storageKey): string
    {
        $key = $this->normalizeStorageKey($storageKey);
        $root = $this->storageRoot();
        $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $key);
        $rootPrefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if (!str_starts_with($path, $rootPrefix)) {
            throw new MediaException('Media storage key is invalid.');
        }

        return $path;
    }

    private function normalizeStorageKey(string $storageKey): string
    {
        $key = str_replace('\\', '/', trim($storageKey));

        if (
            $key === ''
            || str_starts_with($key, '/')
            || str_contains($key, "\0")
            || str_contains($key, '../')
            || str_contains($key, '/..')
            || str_contains($key, '//')
        ) {
            throw new MediaException('Media storage key is invalid.');
        }

        foreach (explode('/', $key) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new MediaException('Media storage key is invalid.');
            }
        }

        return $key;
    }

    private function storageRoot(): string
    {
        if ($this->rootPath !== null) {
            return rtrim($this->rootPath, DIRECTORY_SEPARATOR);
        }

        $basePath = dirname(__DIR__, 3);
        $configured = (string) Config::get('media.local_root', 'storage/media');
        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($configured));

        if ($normalized === '' || preg_match('/^[A-Za-z]:\\\\|^\//', $normalized) === 1) {
            throw new MediaException('Media storage root must be a relative private path.');
        }

        return $basePath . DIRECTORY_SEPARATOR . trim($normalized, DIRECTORY_SEPARATOR);
    }
}
