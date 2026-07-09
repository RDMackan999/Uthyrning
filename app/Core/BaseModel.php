<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Base structure for future domain models.
 *
 * Sprint 1E intentionally does not persist data or execute SQL.
 */
abstract class BaseModel
{
    /**
     * @var array<string, mixed>
     */
    protected array $attributes = [];

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [])
    {
        $this->fill($data);
    }

    /**
     * Return the table name for the concrete model.
     */
    public static function tableName(): string
    {
        throw ModelException::notImplemented(static::class . '::tableName()');
    }

    /**
     * Return the primary key column name.
     */
    public static function primaryKey(): string
    {
        return 'id';
    }

    /**
     * Fill model attributes without persisting them.
     *
     * @param array<string, mixed> $data
     */
    public function fill(array $data): static
    {
        foreach ($data as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $this->attributes[$key] = $value;
        }

        return $this;
    }

    /**
     * Return model attributes as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * Placeholder for future persistence.
     */
    public function save(): void
    {
        throw ModelException::notImplemented(static::class . '::save()');
    }

    /**
     * Placeholder for future deletion.
     */
    public function delete(): void
    {
        throw ModelException::notImplemented(static::class . '::delete()');
    }

    /**
     * Placeholder for future single-record lookup.
     */
    public static function find(int|string $id): static
    {
        throw ModelException::notImplemented(static::class . '::find()');
    }

    /**
     * Placeholder for future collection lookup.
     */
    public static function findAll(): Collection
    {
        throw ModelException::notImplemented(static::class . '::findAll()');
    }
}
