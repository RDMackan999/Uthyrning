<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Core\Collection;
use App\Core\Database;
use App\Core\ModelException;
use App\Models\RentalFulfillmentItem;
use PDO;

/**
 * Repository for fulfillment item condition snapshots.
 */
final class RentalFulfillmentItemRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(RentalFulfillmentItem::class);
    }

    /**
     * Find a fulfillment item by primary key.
     */
    public function findById(int|string $id): RentalFulfillmentItem
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM rental_fulfillment_items WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $statement->execute(['id' => $id]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new ModelException('Rental fulfillment item not found.');
        }

        return new RentalFulfillmentItem($row);
    }

    /**
     * Find all item snapshots for one fulfillment.
     *
     * @return Collection<RentalFulfillmentItem>
     */
    public function findForFulfillment(int $fulfillmentId): Collection
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM rental_fulfillment_items
             WHERE rental_fulfillment_id = :rental_fulfillment_id
                AND deleted_at IS NULL
             ORDER BY id ASC'
        );
        $statement->execute(['rental_fulfillment_id' => $fulfillmentId]);

        return $this->itemsFromRows($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Find item snapshots with booking item dates for admin history display.
     *
     * @return list<array<string, mixed>>
     */
    public function findAdminForFulfillment(int $fulfillmentId): array
    {
        $statement = Database::pdo()->prepare(
            'SELECT rental_fulfillment_items.*,
                booking_items.start_date,
                booking_items.end_date
             FROM rental_fulfillment_items
             INNER JOIN booking_items
                ON booking_items.id = rental_fulfillment_items.booking_item_id
             WHERE rental_fulfillment_items.rental_fulfillment_id = :rental_fulfillment_id
                AND rental_fulfillment_items.deleted_at IS NULL
             ORDER BY rental_fulfillment_items.id ASC'
        );
        $statement->execute(['rental_fulfillment_id' => $fulfillmentId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }

    /**
     * Create an immutable handover snapshot for one booking item.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): RentalFulfillmentItem
    {
        $statement = Database::pdo()->prepare(
            'INSERT INTO rental_fulfillment_items (
                rental_fulfillment_id,
                booking_item_id,
                rental_item_id,
                item_public_id_snapshot,
                item_name_snapshot,
                handover_condition_key,
                handover_condition_note,
                meter_value_handover,
                created_at,
                updated_at
            ) VALUES (
                :rental_fulfillment_id,
                :booking_item_id,
                :rental_item_id,
                :item_public_id_snapshot,
                :item_name_snapshot,
                :handover_condition_key,
                :handover_condition_note,
                :meter_value_handover,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
            )'
        );
        $statement->execute([
            'rental_fulfillment_id' => $this->requiredInt($data['rental_fulfillment_id'] ?? null, 'rental_fulfillment_id'),
            'booking_item_id' => $this->requiredInt($data['booking_item_id'] ?? null, 'booking_item_id'),
            'rental_item_id' => $this->requiredInt($data['rental_item_id'] ?? null, 'rental_item_id'),
            'item_public_id_snapshot' => $this->requiredString($data['item_public_id_snapshot'] ?? null, 'item_public_id_snapshot'),
            'item_name_snapshot' => $this->requiredString($data['item_name_snapshot'] ?? null, 'item_name_snapshot'),
            'handover_condition_key' => $this->requiredString($data['handover_condition_key'] ?? null, 'handover_condition_key'),
            'handover_condition_note' => $this->nullableString($data['handover_condition_note'] ?? null),
            'meter_value_handover' => $this->nullableDecimal($data['meter_value_handover'] ?? null),
        ]);

        return $this->findById((int) Database::pdo()->lastInsertId());
    }

    /**
     * Add return condition facts without mutating handover snapshots.
     *
     * @param array<string, mixed> $data
     */
    public function recordReturn(int $id, array $data): RentalFulfillmentItem
    {
        $statement = Database::pdo()->prepare(
            'UPDATE rental_fulfillment_items
             SET return_condition_key = :return_condition_key,
                return_condition_note = :return_condition_note,
                has_return_deviation = :has_return_deviation,
                damage_note = :damage_note,
                meter_value_return = :meter_value_return,
                updated_at = UTC_TIMESTAMP()
             WHERE id = :id
                AND return_condition_key IS NULL
                AND deleted_at IS NULL'
        );
        $statement->execute([
            'id' => $id,
            'return_condition_key' => $this->requiredString($data['return_condition_key'] ?? null, 'return_condition_key'),
            'return_condition_note' => $this->nullableString($data['return_condition_note'] ?? null),
            'has_return_deviation' => filter_var($data['has_return_deviation'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
            'damage_note' => $this->nullableString($data['damage_note'] ?? null),
            'meter_value_return' => $this->nullableDecimal($data['meter_value_return'] ?? null),
        ]);

        if ($statement->rowCount() !== 1) {
            throw new ModelException('Rental fulfillment item return could not be recorded.');
        }

        return $this->findById($id);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return Collection<RentalFulfillmentItem>
     */
    private function itemsFromRows(array $rows): Collection
    {
        return new Collection(array_map(
            static fn (array $row): RentalFulfillmentItem => new RentalFulfillmentItem($row),
            $rows
        ));
    }

    private function requiredInt(mixed $value, string $field): int
    {
        if ($value === null || $value === '') {
            throw new ModelException($field . ' is required.');
        }

        return (int) $value;
    }

    private function requiredString(mixed $value, string $field): string
    {
        $text = trim((string) $value);

        if ($text === '') {
            throw new ModelException($field . ' is required.');
        }

        return $text;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function nullableDecimal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value) || (float) $value < 0) {
            throw new ModelException('Decimal value must be zero or greater.');
        }

        return number_format((float) $value, 2, '.', '');
    }
}
