<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Base structure for future repository classes.
 *
 * Sprint 1E intentionally contains no query implementation.
 */
abstract class BaseRepository
{
    /**
     * @param class-string<BaseModel>|null $modelClass
     */
    public function __construct(protected ?string $modelClass = null)
    {
    }

    /**
     * Placeholder for future primary-key lookup.
     */
    public function findById(int|string $id): BaseModel
    {
        throw ModelException::notImplemented(static::class . '::findById()');
    }

    /**
     * Placeholder for future collection lookup.
     */
    public function findAll(): Collection
    {
        throw ModelException::notImplemented(static::class . '::findAll()');
    }

    /**
     * Placeholder for future creation.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): BaseModel
    {
        throw ModelException::notImplemented(static::class . '::create()');
    }

    /**
     * Placeholder for future update.
     *
     * @param array<string, mixed> $data
     */
    public function update(int|string $id, array $data): BaseModel
    {
        throw ModelException::notImplemented(static::class . '::update()');
    }

    /**
     * Placeholder for future deletion.
     */
    public function delete(int|string $id): bool
    {
        throw ModelException::notImplemented(static::class . '::delete()');
    }
}
