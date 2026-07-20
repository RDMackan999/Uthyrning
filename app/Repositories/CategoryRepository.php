<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Core\Collection;
use App\Core\Database;
use App\Core\ModelException;
use App\Models\Category;
use PDO;

/**
 * Repository for rental item categories.
 */
final class CategoryRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(Category::class);
    }

    /**
     * Find a non-deleted category by primary key.
     */
    public function findById(int|string $id): Category
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM item_categories WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $statement->execute(['id' => $id]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new ModelException('Category not found.');
        }

        return new Category($row);
    }

    /**
     * Find an active category by slug within one exact scope.
     */
    public function findBySlug(string $slug, ?int $organizationId = null): ?Category
    {
        if ($organizationId === null) {
            $statement = Database::pdo()->prepare(
                'SELECT * FROM item_categories
                 WHERE organization_id IS NULL
                    AND slug = :slug
                    AND is_active = 1
                    AND deleted_at IS NULL
                 LIMIT 1'
            );
            $statement->execute(['slug' => $this->normalizeSlug($slug)]);
        } else {
            $statement = Database::pdo()->prepare(
                'SELECT * FROM item_categories
                 WHERE organization_id = :organization_id
                    AND slug = :slug
                    AND is_active = 1
                    AND deleted_at IS NULL
                 LIMIT 1'
            );
            $statement->execute([
                'organization_id' => $organizationId,
                'slug' => $this->normalizeSlug($slug),
            ]);
        }

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : new Category($row);
    }

    /**
     * Find active categories for a scope.
     */
    public function findAllActive(?int $organizationId = null): Collection
    {
        if ($organizationId === null) {
            return $this->findGlobal();
        }

        return $this->findForOrganization($organizationId);
    }

    /**
     * Find active global platform categories.
     */
    public function findGlobal(): Collection
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM item_categories
             WHERE organization_id IS NULL
                AND is_active = 1
                AND deleted_at IS NULL
             ORDER BY sort_order ASC, name ASC'
        );
        $statement->execute();

        return $this->categoriesFromRows($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Find active categories available for one organization, including global categories.
     */
    public function findForOrganization(int $organizationId): Collection
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM item_categories
             WHERE (organization_id IS NULL OR organization_id = :organization_id)
                AND is_active = 1
                AND deleted_at IS NULL
             ORDER BY organization_id IS NULL DESC, sort_order ASC, name ASC'
        );
        $statement->execute(['organization_id' => $organizationId]);

        return $this->categoriesFromRows($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Find an active category that can be used by one organization.
     */
    public function findAvailableForOrganizationById(int $categoryId, int $organizationId): ?Category
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM item_categories
             WHERE id = :category_id
                AND is_active = 1
                AND deleted_at IS NULL
                AND (organization_id IS NULL OR organization_id = :organization_id)
             LIMIT 1'
        );
        $statement->execute([
            'category_id' => $categoryId,
            'organization_id' => $organizationId,
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : new Category($row);
    }

    /**
     * Create a category without creating related application features.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): Category
    {
        $statement = Database::pdo()->prepare(
            'INSERT INTO item_categories (
                organization_id,
                organization_scope_key,
                slug,
                name,
                description,
                sort_order,
                is_active,
                created_at,
                updated_at
            ) VALUES (
                :organization_id,
                :organization_scope_key,
                :slug,
                :name,
                :description,
                :sort_order,
                :is_active,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
            )'
        );
        $organizationId = $this->nullableInt($data['organization_id'] ?? null);
        $statement->execute([
            'organization_id' => $organizationId,
            'organization_scope_key' => $this->scopeKey($organizationId),
            'slug' => $this->normalizeSlug((string) ($data['slug'] ?? '')),
            'name' => trim((string) ($data['name'] ?? '')),
            'description' => $this->nullableString($data['description'] ?? null),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => $this->boolInt($data['is_active'] ?? true),
        ]);

        return $this->findById((int) Database::pdo()->lastInsertId());
    }

    /**
     * Update allowed category fields.
     *
     * @param array<string, mixed> $data
     */
    public function update(int|string $id, array $data): Category
    {
        $allowedFields = [
            'organization_id',
            'slug',
            'name',
            'description',
            'sort_order',
            'is_active',
        ];

        $assignments = [];
        $params = ['id' => $id];

        foreach ($allowedFields as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            $assignments[] = $field . ' = :' . $field;
            $params[$field] = $this->prepareFieldValue($field, $data[$field]);

            if ($field === 'organization_id') {
                $assignments[] = 'organization_scope_key = :organization_scope_key';
                $params['organization_scope_key'] = $this->scopeKey($params[$field]);
            }
        }

        if ($assignments === []) {
            return $this->findById($id);
        }

        $statement = Database::pdo()->prepare(
            'UPDATE item_categories
             SET ' . implode(', ', $assignments) . ',
                updated_at = UTC_TIMESTAMP()
             WHERE id = :id
                AND deleted_at IS NULL'
        );
        $statement->execute($params);

        return $this->findById($id);
    }

    /**
     * Soft delete a category and make it unavailable for new use.
     */
    public function delete(int|string $id): bool
    {
        $statement = Database::pdo()->prepare(
            'UPDATE item_categories
             SET is_active = 0,
                deleted_at = UTC_TIMESTAMP(),
                updated_at = UTC_TIMESTAMP()
             WHERE id = :id
                AND deleted_at IS NULL'
        );
        $statement->execute(['id' => $id]);

        return $statement->rowCount() > 0;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return Collection<Category>
     */
    private function categoriesFromRows(array $rows): Collection
    {
        return new Collection(array_map(
            static fn (array $row): Category => new Category($row),
            $rows
        ));
    }

    private function normalizeSlug(string $slug): string
    {
        return strtolower(trim($slug));
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function boolInt(mixed $value): int
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    }

    private function scopeKey(?int $organizationId): string
    {
        return $organizationId === null ? 'global' : 'organization:' . $organizationId;
    }

    private function prepareFieldValue(string $field, mixed $value): mixed
    {
        return match ($field) {
            'organization_id' => $this->nullableInt($value),
            'slug' => $this->normalizeSlug((string) $value),
            'name' => trim((string) $value),
            'description' => $this->nullableString($value),
            'sort_order' => (int) $value,
            'is_active' => $this->boolInt($value),
            default => $value,
        };
    }
}
