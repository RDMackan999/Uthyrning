<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Core\Collection;
use App\Core\Database;
use App\Core\ModelException;
use App\Models\ItemAvailabilityBlock;
use PDO;

/**
 * Repository for manual rental item availability blocks.
 */
final class ItemAvailabilityBlockRepository extends BaseRepository
{
    /**
     * @var list<string>
     */
    private const REASON_CODES = ['manual', 'maintenance', 'owner_use', 'transport'];

    public function __construct()
    {
        parent::__construct(ItemAvailabilityBlock::class);
    }

    /**
     * Find a non-deleted availability block by primary key.
     */
    public function findById(int|string $id, ?int $organizationId = null): ItemAvailabilityBlock
    {
        $sql = 'SELECT * FROM blocked_periods WHERE id = :id AND deleted_at IS NULL';
        $params = ['id' => $id];

        if ($organizationId !== null) {
            $sql .= ' AND organization_id = :organization_id';
            $params['organization_id'] = $organizationId;
        }

        $statement = Database::pdo()->prepare($sql . ' LIMIT 1');
        $statement->execute($params);

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new ModelException('Availability block not found.');
        }

        return new ItemAvailabilityBlock($row);
    }

    /**
     * Find one non-deleted block scoped to an item and organization.
     */
    public function findByIdForItem(int $organizationId, int $rentalItemId, int|string $id): ItemAvailabilityBlock
    {
        $statement = Database::pdo()->prepare(
            'SELECT *
             FROM blocked_periods
             WHERE id = :id
                AND organization_id = :organization_id
                AND rental_item_id = :rental_item_id
                AND deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute([
            'id' => $id,
            'organization_id' => $organizationId,
            'rental_item_id' => $rentalItemId,
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new ModelException('Availability block not found.');
        }

        return new ItemAvailabilityBlock($row);
    }

    /**
     * Find non-deleted manual blocks for one rental item in organization scope.
     */
    public function findForItem(int $organizationId, int $rentalItemId): Collection
    {
        $statement = Database::pdo()->prepare(
            'SELECT *
             FROM blocked_periods
             WHERE organization_id = :organization_id
                AND rental_item_id = :rental_item_id
                AND deleted_at IS NULL
             ORDER BY start_date ASC, end_date ASC, id ASC'
        );
        $statement->execute([
            'organization_id' => $organizationId,
            'rental_item_id' => $rentalItemId,
        ]);

        return $this->blocksFromRows($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Find non-deleted manual blocks that overlap an inclusive date range.
     */
    public function findBlockingForItemAndRange(
        int $organizationId,
        int $rentalItemId,
        string $startDate,
        string $endDate,
        ?string $reasonCode = null
    ): Collection {
        $normalizedStartDate = $this->normalizeDate($startDate, 'start_date');
        $normalizedEndDate = $this->normalizeDate($endDate, 'end_date');
        $this->ensureValidDateRange($normalizedStartDate, $normalizedEndDate);

        $sql = 'SELECT *
                FROM blocked_periods
                WHERE organization_id = :organization_id
                    AND rental_item_id = :rental_item_id
                    AND :start_date <= end_date
                    AND :end_date >= start_date
                    AND deleted_at IS NULL';
        $params = [
            'organization_id' => $organizationId,
            'rental_item_id' => $rentalItemId,
            'start_date' => $normalizedStartDate,
            'end_date' => $normalizedEndDate,
        ];

        if ($reasonCode !== null) {
            $sql .= ' AND reason_code = :reason_code';
            $params['reason_code'] = $this->normalizeReasonCode($reasonCode);
        }

        $statement = Database::pdo()->prepare($sql . ' ORDER BY start_date ASC, id ASC');
        $statement->execute($params);

        return $this->blocksFromRows($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Check whether an inclusive date range is blocked manually.
     */
    public function hasBlockingOverlap(
        int $organizationId,
        int $rentalItemId,
        string $startDate,
        string $endDate
    ): bool {
        return $this->findBlockingForItemAndRange($organizationId, $rentalItemId, $startDate, $endDate)->count() > 0;
    }

    /**
     * Create a manual availability block after validating tenant scope.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): ItemAvailabilityBlock
    {
        $organizationId = $this->requiredInt($data['organization_id'] ?? null, 'organization_id');
        $rentalItemId = $this->requiredInt($data['rental_item_id'] ?? null, 'rental_item_id');
        $this->ensureRentalItemBelongsToOrganization($rentalItemId, $organizationId);

        $startDate = $this->normalizeDate((string) ($data['start_date'] ?? ''), 'start_date');
        $endDate = $this->normalizeDate((string) ($data['end_date'] ?? ''), 'end_date');
        $this->ensureValidDateRange($startDate, $endDate);

        $statement = Database::pdo()->prepare(
            'INSERT INTO blocked_periods (
                organization_id,
                rental_item_id,
                start_date,
                end_date,
                reason_code,
                internal_note,
                created_by_user_id,
                created_at,
                updated_at
            ) VALUES (
                :organization_id,
                :rental_item_id,
                :start_date,
                :end_date,
                :reason_code,
                :internal_note,
                :created_by_user_id,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
            )'
        );
        $statement->execute([
            'organization_id' => $organizationId,
            'rental_item_id' => $rentalItemId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'reason_code' => $this->normalizeReasonCode((string) ($data['reason_code'] ?? 'manual')),
            'internal_note' => $this->nullableString($data['internal_note'] ?? null),
            'created_by_user_id' => $this->nullableInt($data['created_by_user_id'] ?? null),
        ]);

        return $this->findById((int) Database::pdo()->lastInsertId(), $organizationId);
    }

    /**
     * Soft delete one manual availability block.
     */
    public function delete(int|string $id, ?int $organizationId = null): bool
    {
        $sql = 'UPDATE blocked_periods
                SET deleted_at = UTC_TIMESTAMP(),
                    updated_at = UTC_TIMESTAMP()
                WHERE id = :id
                    AND deleted_at IS NULL';
        $params = ['id' => $id];

        if ($organizationId !== null) {
            $sql .= ' AND organization_id = :organization_id';
            $params['organization_id'] = $organizationId;
        }

        $statement = Database::pdo()->prepare($sql);
        $statement->execute($params);

        return $statement->rowCount() > 0;
    }

    /**
     * Return the supported Version 1 reason codes for admin forms.
     *
     * @return array<string, string>
     */
    public static function reasonOptions(): array
    {
        return [
            'manual' => 'Manuell blockering',
            'maintenance' => 'Underhåll',
            'owner_use' => 'Egen användning',
            'transport' => 'Transport',
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return Collection<ItemAvailabilityBlock>
     */
    private function blocksFromRows(array $rows): Collection
    {
        return new Collection(array_map(
            static fn (array $row): ItemAvailabilityBlock => new ItemAvailabilityBlock($row),
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

    private function normalizeDate(string $date, string $field): string
    {
        $normalized = trim($date);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized)) {
            throw new ModelException($field . ' must be a YYYY-MM-DD date.');
        }

        return $normalized;
    }

    private function ensureValidDateRange(string $startDate, string $endDate): void
    {
        if ($startDate > $endDate) {
            throw new ModelException('Availability block start_date must be before or equal to end_date.');
        }
    }

    private function normalizeReasonCode(string $reasonCode): string
    {
        $normalized = strtolower(trim($reasonCode));

        if (!in_array($normalized, self::REASON_CODES, true)) {
            throw new ModelException('Availability block reason is not supported in Version 1.');
        }

        return $normalized;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : substr($text, 0, 1000);
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
}
