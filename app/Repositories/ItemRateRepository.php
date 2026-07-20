<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Core\Collection;
use App\Core\Database;
use App\Core\ModelException;
use App\Models\ItemRate;
use PDO;

/**
 * Repository for rental item rate foundation records.
 */
final class ItemRateRepository extends BaseRepository
{
    /**
     * @var list<string>
     */
    private const ALLOWED_RATE_TYPES = ['daily', 'weekend', 'weekly', 'monthly'];

    public function __construct()
    {
        parent::__construct(ItemRate::class);
    }

    /**
     * Find a non-deleted item rate, optionally scoped by organization.
     */
    public function findById(int|string $id, ?int $organizationId = null): ItemRate
    {
        $sql = 'SELECT item_rates.*
                FROM item_rates
                INNER JOIN rental_items ON rental_items.id = item_rates.rental_item_id
                WHERE item_rates.id = :id
                    AND item_rates.deleted_at IS NULL
                    AND rental_items.deleted_at IS NULL';
        $params = ['id' => $id];

        if ($organizationId !== null) {
            $sql .= ' AND rental_items.organization_id = :organization_id';
            $params['organization_id'] = $organizationId;
        }

        $sql .= ' LIMIT 1';

        $statement = Database::pdo()->prepare($sql);
        $statement->execute($params);

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new ModelException('Item rate not found.');
        }

        return new ItemRate($row);
    }

    /**
     * Find a non-deleted item rate scoped to one rental item and organization.
     */
    public function findByIdForItem(int $organizationId, int $rentalItemId, int|string $id): ItemRate
    {
        $statement = Database::pdo()->prepare(
            'SELECT item_rates.*
             FROM item_rates
             INNER JOIN rental_items ON rental_items.id = item_rates.rental_item_id
             WHERE item_rates.id = :id
                AND item_rates.rental_item_id = :rental_item_id
                AND rental_items.organization_id = :organization_id
                AND item_rates.deleted_at IS NULL
                AND rental_items.deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute([
            'id' => $id,
            'organization_id' => $organizationId,
            'rental_item_id' => $rentalItemId,
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new ModelException('Item rate not found.');
        }

        return new ItemRate($row);
    }

    /**
     * Find non-deleted rates for one rental item in one organization scope.
     */
    public function findForItem(int $organizationId, int $rentalItemId): Collection
    {
        $statement = Database::pdo()->prepare(
            'SELECT item_rates.*
             FROM item_rates
             INNER JOIN rental_items ON rental_items.id = item_rates.rental_item_id
             WHERE item_rates.rental_item_id = :rental_item_id
                AND rental_items.organization_id = :organization_id
                AND item_rates.deleted_at IS NULL
                AND rental_items.deleted_at IS NULL
             ORDER BY item_rates.rate_type ASC, item_rates.id ASC'
        );
        $statement->execute([
            'organization_id' => $organizationId,
            'rental_item_id' => $rentalItemId,
        ]);

        return $this->ratesFromRows($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Find active rates for one rental item in one organization scope.
     */
    public function findActiveForItem(int $organizationId, int $rentalItemId): Collection
    {
        $statement = Database::pdo()->prepare(
            'SELECT item_rates.*
             FROM item_rates
             INNER JOIN rental_items ON rental_items.id = item_rates.rental_item_id
             WHERE item_rates.rental_item_id = :rental_item_id
                AND rental_items.organization_id = :organization_id
                AND item_rates.is_active = 1
                AND item_rates.deleted_at IS NULL
                AND rental_items.deleted_at IS NULL
             ORDER BY item_rates.rate_type ASC, item_rates.id ASC'
        );
        $statement->execute([
            'organization_id' => $organizationId,
            'rental_item_id' => $rentalItemId,
        ]);

        return $this->ratesFromRows($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Check whether one rental item has an active, non-deleted daily rate.
     */
    public function hasActiveDailyRate(int $organizationId, int $rentalItemId): bool
    {
        return $this->activeRateTypeExists($organizationId, $rentalItemId, 'daily');
    }

    /**
     * Check whether one active rate type already exists for an item.
     */
    public function activeRateTypeExists(
        int $organizationId,
        int $rentalItemId,
        string $rateType,
        ?int $exceptRateId = null
    ): bool {
        $sql = 'SELECT COUNT(*)
                FROM item_rates
                INNER JOIN rental_items ON rental_items.id = item_rates.rental_item_id
                WHERE item_rates.rental_item_id = :rental_item_id
                    AND rental_items.organization_id = :organization_id
                    AND item_rates.rate_type = :rate_type
                    AND item_rates.is_active = 1
                    AND item_rates.deleted_at IS NULL
                    AND rental_items.deleted_at IS NULL';
        $params = [
            'organization_id' => $organizationId,
            'rental_item_id' => $rentalItemId,
            'rate_type' => $this->normalizeRateType($rateType),
        ];

        if ($exceptRateId !== null && $exceptRateId > 0) {
            $sql .= ' AND item_rates.id != :except_rate_id';
            $params['except_rate_id'] = $exceptRateId;
        }

        $statement = Database::pdo()->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn() > 0;
    }

    /**
     * Create a Version 1 item rate after validating rental item organization scope.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): ItemRate
    {
        $organizationId = $this->requiredInt($data['organization_id'] ?? null, 'organization_id');
        $rentalItemId = $this->requiredInt($data['rental_item_id'] ?? null, 'rental_item_id');
        $this->ensureRentalItemBelongsToOrganization($rentalItemId, $organizationId);
        $rateType = $this->normalizeRateType((string) ($data['rate_type'] ?? ''));
        $amount = $this->decimal($data['amount'] ?? null);
        $currency = $this->normalizeCurrency((string) ($data['currency'] ?? 'SEK'));
        $isActive = $this->boolInt($data['is_active'] ?? true);

        if ($isActive === 1 && $this->activeRateTypeExists($organizationId, $rentalItemId, $rateType)) {
            throw new ModelException('An active item rate for this type already exists.');
        }

        $statement = Database::pdo()->prepare(
            'INSERT INTO item_rates (
                rental_item_id,
                rate_type,
                amount,
                currency,
                is_active,
                created_at,
                updated_at
            ) VALUES (
                :rental_item_id,
                :rate_type,
                :amount,
                :currency,
                :is_active,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
            )'
        );
        $statement->execute([
            'rental_item_id' => $rentalItemId,
            'rate_type' => $rateType,
            'amount' => $amount,
            'currency' => $currency,
            'is_active' => $isActive,
        ]);

        return $this->findById((int) Database::pdo()->lastInsertId(), $organizationId);
    }

    /**
     * Update an item rate. Pass organization id to enforce tenant scope.
     *
     * @param array<string, mixed> $data
     */
    public function update(int|string $id, array $data, ?int $organizationId = null): ItemRate
    {
        $current = $this->findById($id, $organizationId);
        $currentData = $current->toArray();
        $rentalItemId = (int) ($currentData['rental_item_id'] ?? 0);
        $targetRateType = array_key_exists('rate_type', $data)
            ? $this->normalizeRateType((string) $data['rate_type'])
            : (string) ($currentData['rate_type'] ?? '');
        $targetIsActive = array_key_exists('is_active', $data)
            ? $this->boolInt($data['is_active'])
            : $this->boolInt($currentData['is_active'] ?? false);

        if (
            $organizationId !== null
            && $targetIsActive === 1
            && $rentalItemId > 0
            && $this->activeRateTypeExists($organizationId, $rentalItemId, $targetRateType, (int) $id)
        ) {
            throw new ModelException('An active item rate for this type already exists.');
        }

        $allowedFields = [
            'rate_type',
            'amount',
            'currency',
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
        }

        if ($assignments === []) {
            return $current;
        }

        $sql = 'UPDATE item_rates
                INNER JOIN rental_items ON rental_items.id = item_rates.rental_item_id
                SET ' . implode(', ', array_map(
            static fn (string $assignment): string => 'item_rates.' . $assignment,
            $assignments
        )) . ',
                    item_rates.updated_at = UTC_TIMESTAMP()
                WHERE item_rates.id = :id
                    AND item_rates.deleted_at IS NULL
                    AND rental_items.deleted_at IS NULL';

        if ($organizationId !== null) {
            $sql .= ' AND rental_items.organization_id = :organization_id';
            $params['organization_id'] = $organizationId;
        }

        $statement = Database::pdo()->prepare($sql);
        $statement->execute($params);

        return $this->findById($id, $organizationId);
    }

    /**
     * Soft delete an item rate. Pass organization id to enforce tenant scope.
     */
    public function delete(int|string $id, ?int $organizationId = null): bool
    {
        $sql = 'UPDATE item_rates
                INNER JOIN rental_items ON rental_items.id = item_rates.rental_item_id
                SET item_rates.is_active = 0,
                    item_rates.deleted_at = UTC_TIMESTAMP(),
                    item_rates.updated_at = UTC_TIMESTAMP()
                WHERE item_rates.id = :id
                    AND item_rates.deleted_at IS NULL
                    AND rental_items.deleted_at IS NULL';
        $params = ['id' => $id];

        if ($organizationId !== null) {
            $sql .= ' AND rental_items.organization_id = :organization_id';
            $params['organization_id'] = $organizationId;
        }

        $statement = Database::pdo()->prepare($sql);
        $statement->execute($params);

        return $statement->rowCount() > 0;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return Collection<ItemRate>
     */
    private function ratesFromRows(array $rows): Collection
    {
        return new Collection(array_map(
            static fn (array $row): ItemRate => new ItemRate($row),
            $rows
        ));
    }

    private function ensureRentalItemBelongsToOrganization(int $rentalItemId, int $organizationId): void
    {
        $statement = Database::pdo()->prepare(
            'SELECT id
             FROM rental_items
             WHERE id = :rental_item_id
                AND organization_id = :organization_id
                AND deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute([
            'rental_item_id' => $rentalItemId,
            'organization_id' => $organizationId,
        ]);

        if ($statement->fetchColumn() === false) {
            throw new ModelException('Rental item is not available for this organization.');
        }
    }

    private function requiredInt(mixed $value, string $field): int
    {
        if ($value === null || $value === '') {
            throw new ModelException($field . ' is required.');
        }

        return (int) $value;
    }

    private function normalizeRateType(string $rateType): string
    {
        $normalized = strtolower(trim($rateType));

        if (!in_array($normalized, self::ALLOWED_RATE_TYPES, true)) {
            throw new ModelException('Rate type is not supported in Version 1.');
        }

        return $normalized;
    }

    private function normalizeCurrency(string $currency): string
    {
        $normalized = strtoupper(substr(trim($currency), 0, 3)) ?: 'SEK';

        if ($normalized !== 'SEK') {
            throw new ModelException('Currency is not supported in Version 1.');
        }

        return $normalized;
    }

    private function boolInt(mixed $value): int
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    }

    private function decimal(mixed $value): string
    {
        if ($value === null || $value === '') {
            throw new ModelException('amount is required.');
        }

        if (!is_numeric($value) || (float) $value <= 0) {
            throw new ModelException('amount must be greater than zero.');
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function prepareFieldValue(string $field, mixed $value): mixed
    {
        return match ($field) {
            'rate_type' => $this->normalizeRateType((string) $value),
            'amount' => $this->decimal($value),
            'currency' => $this->normalizeCurrency((string) $value),
            'is_active' => $this->boolInt($value),
            default => $value,
        };
    }
}
