<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Core\Collection;
use App\Core\Database;
use App\Core\ModelException;
use App\Models\RentalItem;
use App\Services\PublicIdGenerator;
use PDO;
use RuntimeException;

/**
 * Repository for rental item foundation records.
 */
final class RentalItemRepository extends BaseRepository
{
    private const MAX_PUBLIC_ID_ATTEMPTS = 5;

    public function __construct(
        private readonly PublicIdGenerator $publicIdGenerator = new PublicIdGenerator()
    ) {
        parent::__construct(RentalItem::class);
    }

    /**
     * Find a non-deleted rental item by primary key.
     */
    public function findById(int|string $id): RentalItem
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM rental_items WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $statement->execute(['id' => $id]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new ModelException('Rental item not found.');
        }

        return new RentalItem($row);
    }

    /**
     * Find a non-deleted rental item by immutable public id.
     */
    public function findByPublicId(string $publicId): ?RentalItem
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM rental_items
             WHERE public_id = :public_id
                AND deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute(['public_id' => trim($publicId)]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : new RentalItem($row);
    }

    /**
     * Find a non-deleted rental item by slug in one organization scope.
     */
    public function findBySlug(int $organizationId, string $slug): ?RentalItem
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM rental_items
             WHERE organization_id = :organization_id
                AND slug = :slug
                AND deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute([
            'organization_id' => $organizationId,
            'slug' => $this->normalizeSlug($slug),
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : new RentalItem($row);
    }

    /**
     * Find non-deleted rental items for one organization.
     */
    public function findForOrganization(int $organizationId): Collection
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM rental_items
             WHERE organization_id = :organization_id
                AND deleted_at IS NULL
             ORDER BY created_at DESC, id DESC'
        );
        $statement->execute(['organization_id' => $organizationId]);

        return $this->itemsFromRows($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Find active and rentable foundation records for one organization.
     */
    public function findActiveForOrganization(int $organizationId): Collection
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM rental_items
             WHERE organization_id = :organization_id
                AND is_active = 1
                AND is_rentable = 1
                AND deleted_at IS NULL
             ORDER BY name ASC, id ASC'
        );
        $statement->execute(['organization_id' => $organizationId]);

        return $this->itemsFromRows($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Find non-deleted rental items with admin list display fields.
     *
     * @return Collection<RentalItem>
     */
    public function findAllForAdmin(): Collection
    {
        $statement = Database::pdo()->prepare(
            'SELECT rental_items.*,
                organizations.name AS organization_name,
                item_categories.name AS primary_category_name
             FROM rental_items
             INNER JOIN organizations
                ON organizations.id = rental_items.organization_id
             INNER JOIN item_categories
                ON item_categories.id = rental_items.primary_category_id
             WHERE rental_items.deleted_at IS NULL
             ORDER BY rental_items.created_at DESC, rental_items.id DESC'
        );
        $statement->execute();

        return $this->itemsFromRows($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Find rental items that are safe to show in the public listing.
     *
     * @param array{q?: mixed, category?: mixed, category_slug?: mixed} $filters
     * @return Collection<RentalItem>
     */
    public function findPublicListing(array $filters = []): Collection
    {
        $params = $this->publicItemParams();
        $sql = $this->publicItemBaseSql() . $this->publicFilterSql($filters, $params);

        $statement = Database::pdo()->prepare(
            $sql . ' ORDER BY rental_items.created_at DESC,
                rental_items.name ASC,
                rental_items.id ASC'
        );
        $statement->execute($params);

        return $this->itemsFromRows($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Find one public rental item by immutable public id and current slug.
     */
    public function findPublicDetail(string $publicId, string $slug): ?RentalItem
    {
        $statement = Database::pdo()->prepare(
            $this->publicItemBaseSql()
            . ' AND rental_items.public_id = :public_id
                AND rental_items.slug = :slug
             LIMIT 1'
        );
        $statement->execute($this->publicItemParams([
            'public_id' => trim($publicId),
            'slug' => $this->normalizeSlug($slug),
        ]));

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : new RentalItem($row);
    }

    /**
     * Find one item that is bookable under the same Version 1 publication rules.
     */
    public function findBookableForBooking(int $organizationId, int $rentalItemId): RentalItem
    {
        $statement = Database::pdo()->prepare(
            'SELECT rental_items.*
             FROM rental_items
             INNER JOIN organizations
                ON organizations.id = rental_items.organization_id
                AND organizations.status_key = :organization_status
                AND organizations.deleted_at IS NULL
             INNER JOIN item_categories
                ON item_categories.id = rental_items.primary_category_id
                AND item_categories.is_active = 1
                AND item_categories.deleted_at IS NULL
                AND (
                    item_categories.organization_id IS NULL
                    OR item_categories.organization_id = rental_items.organization_id
                )
             INNER JOIN item_rates AS daily_rates
                ON daily_rates.rental_item_id = rental_items.id
                AND daily_rates.rate_type = :daily_rate_type
                AND daily_rates.is_active = 1
                AND daily_rates.deleted_at IS NULL
             WHERE rental_items.id = :rental_item_id
                AND rental_items.organization_id = :organization_id
                AND rental_items.publication_status_key = :publication_status
                AND rental_items.is_active = 1
                AND rental_items.is_rentable = 1
                AND rental_items.deleted_at IS NULL
             ORDER BY daily_rates.id ASC
             LIMIT 1'
        );
        $statement->execute([
            'organization_id' => $organizationId,
            'rental_item_id' => $rentalItemId,
            'organization_status' => 'active',
            'daily_rate_type' => 'daily',
            'publication_status' => 'published',
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new ModelException('Rental item is not bookable.');
        }

        return new RentalItem($row);
    }

    /**
     * Find one bookable item by its public URL identifiers.
     */
    public function findBookableByPublicRoute(string $publicId, string $slug): RentalItem
    {
        $statement = Database::pdo()->prepare(
            'SELECT rental_items.*,
                organizations.name AS organization_name,
                item_categories.name AS primary_category_name,
                daily_rates.amount AS daily_rate_amount,
                daily_rates.currency AS daily_rate_currency
             FROM rental_items
             INNER JOIN organizations
                ON organizations.id = rental_items.organization_id
                AND organizations.status_key = :organization_status
                AND organizations.deleted_at IS NULL
             INNER JOIN item_categories
                ON item_categories.id = rental_items.primary_category_id
                AND item_categories.is_active = 1
                AND item_categories.deleted_at IS NULL
                AND (
                    item_categories.organization_id IS NULL
                    OR item_categories.organization_id = rental_items.organization_id
                )
             INNER JOIN item_rates AS daily_rates
                ON daily_rates.rental_item_id = rental_items.id
                AND daily_rates.rate_type = :daily_rate_type
                AND daily_rates.is_active = 1
                AND daily_rates.deleted_at IS NULL
                AND daily_rates.id = (
                    SELECT MIN(candidate_daily_rates.id)
                    FROM item_rates AS candidate_daily_rates
                    WHERE candidate_daily_rates.rental_item_id = rental_items.id
                        AND candidate_daily_rates.rate_type = :candidate_daily_rate_type
                        AND candidate_daily_rates.is_active = 1
                        AND candidate_daily_rates.deleted_at IS NULL
                )
             WHERE rental_items.public_id = :public_id
                AND rental_items.slug = :slug
                AND rental_items.publication_status_key = :publication_status
                AND rental_items.is_active = 1
                AND rental_items.is_rentable = 1
                AND rental_items.deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute([
            'public_id' => trim($publicId),
            'slug' => $this->normalizeSlug($slug),
            'organization_status' => 'active',
            'daily_rate_type' => 'daily',
            'candidate_daily_rate_type' => 'daily',
            'publication_status' => 'published',
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new ModelException('Rental item is not bookable.');
        }

        return new RentalItem($row);
    }

    /**
     * Create a rental item foundation record.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): RentalItem
    {
        $organizationId = $this->requiredInt($data['organization_id'] ?? null, 'organization_id');
        $primaryCategoryId = $this->requiredInt($data['primary_category_id'] ?? null, 'primary_category_id');
        $this->ensureCategoryAvailableForOrganization($primaryCategoryId, $organizationId);

        $statement = Database::pdo()->prepare(
            'INSERT INTO rental_items (
                public_id,
                organization_id,
                owning_company_id,
                primary_category_id,
                slug,
                name,
                short_name,
                description,
                internal_note,
                manufacturer,
                model,
                serial_number,
                inventory_number,
                status_key,
                publication_status_key,
                condition_grade_id,
                is_active,
                is_rentable,
                vat_rate,
                deposit_amount,
                created_at,
                updated_at
            ) VALUES (
                :public_id,
                :organization_id,
                :owning_company_id,
                :primary_category_id,
                :slug,
                :name,
                :short_name,
                :description,
                :internal_note,
                :manufacturer,
                :model,
                :serial_number,
                :inventory_number,
                :status_key,
                :publication_status_key,
                :condition_grade_id,
                :is_active,
                :is_rentable,
                :vat_rate,
                :deposit_amount,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
            )'
        );
        $statement->execute([
            'public_id' => $this->generateUniquePublicId(),
            'organization_id' => $organizationId,
            'owning_company_id' => $this->nullableInt($data['owning_company_id'] ?? null),
            'primary_category_id' => $primaryCategoryId,
            'slug' => $this->normalizeSlug((string) ($data['slug'] ?? '')),
            'name' => trim((string) ($data['name'] ?? '')),
            'short_name' => $this->nullableString($data['short_name'] ?? null),
            'description' => $this->nullableString($data['description'] ?? null),
            'internal_note' => $this->nullableString($data['internal_note'] ?? null),
            'manufacturer' => $this->nullableString($data['manufacturer'] ?? null),
            'model' => $this->nullableString($data['model'] ?? null),
            'serial_number' => $this->nullableString($data['serial_number'] ?? null),
            'inventory_number' => $this->nullableString($data['inventory_number'] ?? null),
            'status_key' => $this->normalizeKey((string) ($data['status_key'] ?? 'draft')),
            'publication_status_key' => $this->normalizeKey((string) ($data['publication_status_key'] ?? 'draft')),
            'condition_grade_id' => $this->nullableInt($data['condition_grade_id'] ?? null),
            'is_active' => $this->boolInt($data['is_active'] ?? true),
            'is_rentable' => $this->boolInt($data['is_rentable'] ?? false),
            'vat_rate' => $this->nullableDecimal($data['vat_rate'] ?? null),
            'deposit_amount' => $this->nullableDecimal($data['deposit_amount'] ?? null),
        ]);

        return $this->findById((int) Database::pdo()->lastInsertId());
    }

    /**
     * Update allowed rental item fields without changing public_id or organization scope.
     *
     * @param array<string, mixed> $data
     */
    public function update(int|string $id, array $data): RentalItem
    {
        $current = $this->findById($id);
        $currentData = $current->toArray();
        $organizationId = array_key_exists('organization_id', $data)
            ? (int) $data['organization_id']
            : (int) $currentData['organization_id'];
        $primaryCategoryId = array_key_exists('primary_category_id', $data)
            ? (int) $data['primary_category_id']
            : (int) $currentData['primary_category_id'];

        $this->ensureCategoryAvailableForOrganization($primaryCategoryId, $organizationId);

        $allowedFields = [
            'organization_id',
            'owning_company_id',
            'primary_category_id',
            'slug',
            'name',
            'short_name',
            'description',
            'internal_note',
            'manufacturer',
            'model',
            'serial_number',
            'inventory_number',
            'status_key',
            'publication_status_key',
            'condition_grade_id',
            'is_active',
            'is_rentable',
            'vat_rate',
            'deposit_amount',
        ];

        $assignments = [];
        $params = ['id' => $id];

        foreach ($allowedFields as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            $assignments[] = $field . ' = :' . $field;
            $params[$field] = $this->prepareFieldValue($field, $data[$field]);
        }

        if ($assignments === []) {
            return $current;
        }

        $statement = Database::pdo()->prepare(
            'UPDATE rental_items
             SET ' . implode(', ', $assignments) . ',
                updated_at = UTC_TIMESTAMP()
             WHERE id = :id
                AND deleted_at IS NULL'
        );
        $statement->execute($params);

        return $this->findById($id);
    }

    /**
     * Persist the publication status selected by the domain service.
     */
    public function updatePublicationStatus(int|string $id, string $publicationStatusKey): RentalItem
    {
        $statement = Database::pdo()->prepare(
            'UPDATE rental_items
             SET publication_status_key = :publication_status_key,
                updated_at = UTC_TIMESTAMP()
             WHERE id = :id
                AND deleted_at IS NULL'
        );
        $statement->execute([
            'id' => $id,
            'publication_status_key' => $this->normalizeKey($publicationStatusKey),
        ]);

        return $this->findById($id);
    }

    /**
     * Soft delete a rental item without removing history.
     */
    public function delete(int|string $id): bool
    {
        $statement = Database::pdo()->prepare(
            'UPDATE rental_items
             SET is_active = 0,
                is_rentable = 0,
                publication_status_key = :publication_status_key,
                deleted_at = UTC_TIMESTAMP(),
                updated_at = UTC_TIMESTAMP()
             WHERE id = :id
                AND deleted_at IS NULL'
        );
        $statement->execute([
            'id' => $id,
            'publication_status_key' => 'archived',
        ]);

        return $statement->rowCount() > 0;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return Collection<RentalItem>
     */
    private function itemsFromRows(array $rows): Collection
    {
        return new Collection(array_map(
            static fn (array $row): RentalItem => new RentalItem($row),
            $rows
        ));
    }

    private function publicItemBaseSql(): string
    {
        return 'SELECT rental_items.public_id,
                rental_items.slug,
                rental_items.name,
                rental_items.short_name,
                rental_items.description,
                rental_items.deposit_amount,
                rental_items.created_at,
                rental_items.updated_at,
                organizations.name AS organization_name,
                item_categories.slug AS primary_category_slug,
                item_categories.organization_id AS primary_category_organization_id,
                item_categories.name AS primary_category_name,
                daily_rates.amount AS daily_rate_amount,
                daily_rates.currency AS daily_rate_currency
             FROM rental_items
             INNER JOIN organizations
                ON organizations.id = rental_items.organization_id
                AND organizations.status_key = :organization_status
                AND organizations.deleted_at IS NULL
             INNER JOIN item_categories
                ON item_categories.id = rental_items.primary_category_id
                AND item_categories.is_active = 1
                AND item_categories.deleted_at IS NULL
                AND (
                    item_categories.organization_id IS NULL
                    OR item_categories.organization_id = rental_items.organization_id
                )
             INNER JOIN item_rates AS daily_rates
                ON daily_rates.rental_item_id = rental_items.id
                AND daily_rates.rate_type = :daily_rate_type
                AND daily_rates.is_active = 1
                AND daily_rates.deleted_at IS NULL
                AND daily_rates.id = (
                    SELECT MIN(candidate_daily_rates.id)
                    FROM item_rates AS candidate_daily_rates
                    WHERE candidate_daily_rates.rental_item_id = rental_items.id
                        AND candidate_daily_rates.rate_type = :candidate_daily_rate_type
                        AND candidate_daily_rates.is_active = 1
                        AND candidate_daily_rates.deleted_at IS NULL
                )
             WHERE rental_items.publication_status_key = :publication_status
                AND rental_items.is_active = 1
                AND rental_items.is_rentable = 1
                AND rental_items.deleted_at IS NULL';
    }

    /**
     * Add safe public filters without weakening the base publication rules.
     *
     * @param array{q?: mixed, category?: mixed, category_slug?: mixed} $filters
     * @param array<string, mixed> $params
     */
    private function publicFilterSql(array $filters, array &$params): string
    {
        $sql = '';
        $searchQuery = $this->normalizeSearchQuery($filters['q'] ?? null);

        if ($searchQuery !== '') {
            $params['search_query'] = '%' . $this->escapeLike($searchQuery) . '%';
            $sql .= ' AND (
                    rental_items.name LIKE :search_query ESCAPE \'\\\\\'
                    OR COALESCE(rental_items.short_name, \'\') LIKE :search_query ESCAPE \'\\\\\'
                    OR COALESCE(rental_items.description, \'\') LIKE :search_query ESCAPE \'\\\\\'
                )';
        }

        $categorySlug = $this->normalizeSlug((string) ($filters['category_slug'] ?? $filters['category'] ?? ''));

        if ($categorySlug !== '') {
            $params['category_slug'] = $categorySlug;
            $sql .= ' AND item_categories.slug = :category_slug';
        }

        return $sql;
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function publicItemParams(array $overrides = []): array
    {
        return array_merge([
            'organization_status' => 'active',
            'daily_rate_type' => 'daily',
            'candidate_daily_rate_type' => 'daily',
            'publication_status' => 'published',
        ], $overrides);
    }

    private function generateUniquePublicId(): string
    {
        for ($attempt = 0; $attempt < self::MAX_PUBLIC_ID_ATTEMPTS; $attempt++) {
            $publicId = $this->publicIdGenerator->generate();

            if ($this->findByPublicId($publicId) === null) {
                return $publicId;
            }
        }

        throw new RuntimeException('Could not generate unique rental item public id.');
    }

    private function ensureCategoryAvailableForOrganization(int $categoryId, int $organizationId): void
    {
        $statement = Database::pdo()->prepare(
            'SELECT id
             FROM item_categories
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

        if ($statement->fetchColumn() === false) {
            throw new ModelException('Primary category is not available for this organization.');
        }
    }

    private function normalizeSlug(string $slug): string
    {
        return strtolower(trim($slug));
    }

    private function normalizeSearchQuery(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        $query = trim((string) $value);

        if ($query === '') {
            return '';
        }

        return function_exists('mb_substr') ? mb_substr($query, 0, 100) : substr($query, 0, 100);
    }

    private function escapeLike(string $value): string
    {
        return str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $value
        );
    }

    private function normalizeKey(string $key): string
    {
        return strtolower(trim($key));
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

    private function requiredInt(mixed $value, string $field): int
    {
        if ($value === null || $value === '') {
            throw new ModelException($field . ' is required.');
        }

        return (int) $value;
    }

    private function boolInt(mixed $value): int
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    }

    private function nullableDecimal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function prepareFieldValue(string $field, mixed $value): mixed
    {
        return match ($field) {
            'organization_id' => (int) $value,
            'owning_company_id', 'primary_category_id', 'condition_grade_id' => $this->nullableInt($value),
            'slug' => $this->normalizeSlug((string) $value),
            'name' => trim((string) $value),
            'status_key', 'publication_status_key' => $this->normalizeKey((string) $value),
            'short_name',
            'description',
            'internal_note',
            'manufacturer',
            'model',
            'serial_number',
            'inventory_number' => $this->nullableString($value),
            'is_active', 'is_rentable' => $this->boolInt($value),
            'vat_rate', 'deposit_amount' => $this->nullableDecimal($value),
            default => $value,
        };
    }
}
