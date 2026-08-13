<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Core\Collection;
use App\Core\Database;
use App\Core\ModelException;
use App\Models\BookingItem;
use PDO;

/**
 * Repository for booking item foundation records.
 */
final class BookingItemRepository extends BaseRepository
{
    /**
     * @var list<string>
     */
    private const ALLOWED_RATE_TYPES = ['daily', 'weekend', 'weekly', 'monthly'];

    /**
     * @var list<string>
     */
    private const BLOCKING_STATUS_KEYS = ['request', 'approved', 'active'];

    public function __construct()
    {
        parent::__construct(BookingItem::class);
    }

    /**
     * Find a non-deleted booking item by primary key, optionally scoped by organization.
     */
    public function findById(int|string $id, ?int $organizationId = null): BookingItem
    {
        $sql = 'SELECT booking_items.*
                FROM booking_items
                INNER JOIN bookings ON bookings.id = booking_items.booking_id
                WHERE booking_items.id = :id
                    AND bookings.deleted_at IS NULL';
        $params = ['id' => $id];

        if ($organizationId !== null) {
            $sql .= ' AND bookings.organization_id = :organization_id';
            $params['organization_id'] = $organizationId;
        }

        $statement = Database::pdo()->prepare($sql . ' LIMIT 1');
        $statement->execute($params);

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new ModelException('Booking item not found.');
        }

        return new BookingItem($row);
    }

    /**
     * Find booking items for one booking in one organization scope.
     */
    public function findForBooking(int $organizationId, int $bookingId): Collection
    {
        $statement = Database::pdo()->prepare(
            'SELECT booking_items.*
             FROM booking_items
             INNER JOIN bookings ON bookings.id = booking_items.booking_id
             WHERE booking_items.booking_id = :booking_id
                AND bookings.organization_id = :organization_id
                AND bookings.deleted_at IS NULL
             ORDER BY booking_items.id ASC'
        );
        $statement->execute([
            'organization_id' => $organizationId,
            'booking_id' => $bookingId,
        ]);

        return $this->itemsFromRows($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Create a booking item and immutable price snapshot.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): BookingItem
    {
        $organizationId = $this->requiredInt($data['organization_id'] ?? null, 'organization_id');
        $bookingId = $this->requiredInt($data['booking_id'] ?? null, 'booking_id');
        $rentalItemId = $this->requiredInt($data['rental_item_id'] ?? null, 'rental_item_id');
        $bookingScope = $this->bookingScope($bookingId, $organizationId);
        $this->ensureRentalItemBelongsToOrganization($rentalItemId, $organizationId);

        $startDate = $this->normalizeDate(
            (string) ($data['start_date'] ?? $bookingScope['start_date']),
            'start_date'
        );
        $endDate = $this->normalizeDate(
            (string) ($data['end_date'] ?? $bookingScope['end_date']),
            'end_date'
        );
        $this->ensureValidDateRange($startDate, $endDate);

        $rateType = $this->normalizeRateType((string) ($data['rate_type'] ?? ''));
        $unitPrice = $this->decimal($data['unit_price'] ?? null);
        $currency = $this->normalizeCurrency((string) ($data['currency'] ?? 'SEK'));
        $quantity = $this->positiveInt($data['quantity'] ?? 1, 'quantity');
        $numberOfUnits = $this->positiveInt($data['number_of_units'] ?? null, 'number_of_units');
        $subtotalAmount = $this->decimal($data['subtotal_amount'] ?? null);
        $depositAmount = $this->nullableDecimal($data['deposit_amount'] ?? null);

        $statement = Database::pdo()->prepare(
            'INSERT INTO booking_items (
                booking_id,
                rental_item_id,
                start_date,
                end_date,
                rate_type,
                unit_price,
                currency,
                quantity,
                number_of_units,
                subtotal_amount,
                deposit_amount,
                created_at,
                updated_at
            ) VALUES (
                :booking_id,
                :rental_item_id,
                :start_date,
                :end_date,
                :rate_type,
                :unit_price,
                :currency,
                :quantity,
                :number_of_units,
                :subtotal_amount,
                :deposit_amount,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
            )'
        );
        $statement->execute([
            'booking_id' => $bookingId,
            'rental_item_id' => $rentalItemId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'rate_type' => $rateType,
            'unit_price' => $unitPrice,
            'currency' => $currency,
            'quantity' => $quantity,
            'number_of_units' => $numberOfUnits,
            'subtotal_amount' => $subtotalAmount,
            'deposit_amount' => $depositAmount,
        ]);

        $bookingItemId = (int) Database::pdo()->lastInsertId();
        $this->createPriceSnapshot(
            $bookingId,
            $bookingItemId,
            $rateType,
            $unitPrice,
            $currency,
            $numberOfUnits,
            $subtotalAmount,
            $depositAmount
        );
        $this->refreshBookingTotals($bookingId);

        return $this->findById($bookingItemId, $organizationId);
    }

    /**
     * Check inclusive date overlap for blocking booking statuses.
     */
    public function hasBlockingOverlap(
        int $organizationId,
        int $rentalItemId,
        string $startDate,
        string $endDate,
        ?int $excludeBookingId = null
    ): bool {
        $normalizedStartDate = $this->normalizeDate($startDate, 'start_date');
        $normalizedEndDate = $this->normalizeDate($endDate, 'end_date');
        $this->ensureValidDateRange($normalizedStartDate, $normalizedEndDate);

        $statusPlaceholders = implode(',', array_fill(0, count(self::BLOCKING_STATUS_KEYS), '?'));
        $params = [
            $organizationId,
            $rentalItemId,
            $normalizedStartDate,
            $normalizedEndDate,
        ];

        foreach (self::BLOCKING_STATUS_KEYS as $statusKey) {
            $params[] = $statusKey;
        }

        $sql = 'SELECT COUNT(*)
                FROM booking_items
                INNER JOIN bookings ON bookings.id = booking_items.booking_id
                WHERE bookings.organization_id = ?
                    AND booking_items.rental_item_id = ?
                    AND ? <= booking_items.end_date
                    AND ? >= booking_items.start_date
                    AND bookings.status_key IN (' . $statusPlaceholders . ')
                    AND bookings.deleted_at IS NULL';

        if ($excludeBookingId !== null) {
            $sql .= ' AND bookings.id != ?';
            $params[] = $excludeBookingId;
        }

        $statement = Database::pdo()->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn() > 0;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return Collection<BookingItem>
     */
    private function itemsFromRows(array $rows): Collection
    {
        return new Collection(array_map(
            static fn (array $row): BookingItem => new BookingItem($row),
            $rows
        ));
    }

    /**
     * @return array{organization_id: int, start_date: string, end_date: string}
     */
    private function bookingScope(int $bookingId, int $organizationId): array
    {
        $statement = Database::pdo()->prepare(
            'SELECT organization_id, start_date, end_date
             FROM bookings
             WHERE id = :booking_id
                AND organization_id = :organization_id
                AND deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute([
            'booking_id' => $bookingId,
            'organization_id' => $organizationId,
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new ModelException('Booking is not available for this organization.');
        }

        return [
            'organization_id' => (int) $row['organization_id'],
            'start_date' => (string) $row['start_date'],
            'end_date' => (string) $row['end_date'],
        ];
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

    private function createPriceSnapshot(
        int $bookingId,
        int $bookingItemId,
        string $rateType,
        string $unitPrice,
        string $currency,
        int $numberOfUnits,
        string $subtotalAmount,
        ?string $depositAmount
    ): void {
        $statement = Database::pdo()->prepare(
            'INSERT INTO booking_price_snapshots (
                booking_id,
                booking_item_id,
                rate_type,
                unit_price,
                currency,
                number_of_units,
                subtotal_amount,
                deposit_amount,
                created_at
            ) VALUES (
                :booking_id,
                :booking_item_id,
                :rate_type,
                :unit_price,
                :currency,
                :number_of_units,
                :subtotal_amount,
                :deposit_amount,
                UTC_TIMESTAMP()
            )'
        );
        $statement->execute([
            'booking_id' => $bookingId,
            'booking_item_id' => $bookingItemId,
            'rate_type' => $rateType,
            'unit_price' => $unitPrice,
            'currency' => $currency,
            'number_of_units' => $numberOfUnits,
            'subtotal_amount' => $subtotalAmount,
            'deposit_amount' => $depositAmount,
        ]);
    }

    private function refreshBookingTotals(int $bookingId): void
    {
        $statement = Database::pdo()->prepare(
            'UPDATE bookings
             SET total_units = (
                    SELECT COALESCE(SUM(number_of_units), 0)
                    FROM booking_items
                    WHERE booking_id = :booking_id_for_units
                ),
                subtotal_amount = (
                    SELECT COALESCE(SUM(subtotal_amount), 0.00)
                    FROM booking_items
                    WHERE booking_id = :booking_id_for_subtotal
                ),
                deposit_amount = (
                    SELECT CASE
                        WHEN COUNT(deposit_amount) = 0 THEN NULL
                        ELSE SUM(COALESCE(deposit_amount, 0.00))
                    END
                    FROM booking_items
                    WHERE booking_id = :booking_id_for_deposit
                ),
                updated_at = UTC_TIMESTAMP()
             WHERE id = :booking_id'
        );
        $statement->execute([
            'booking_id_for_units' => $bookingId,
            'booking_id_for_subtotal' => $bookingId,
            'booking_id_for_deposit' => $bookingId,
            'booking_id' => $bookingId,
        ]);
    }

    private function normalizeRateType(string $rateType): string
    {
        $normalized = strtolower(trim($rateType));

        if (!in_array($normalized, self::ALLOWED_RATE_TYPES, true)) {
            throw new ModelException('Rate type is not supported in Version 1.');
        }

        return $normalized;
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
            throw new ModelException('Booking item start_date must be before or equal to end_date.');
        }
    }

    private function normalizeCurrency(string $currency): string
    {
        $normalized = strtoupper(substr(trim($currency), 0, 3)) ?: 'SEK';

        if ($normalized !== 'SEK') {
            throw new ModelException('Currency is not supported in Version 1.');
        }

        return $normalized;
    }

    private function requiredInt(mixed $value, string $field): int
    {
        if ($value === null || $value === '') {
            throw new ModelException($field . ' is required.');
        }

        return (int) $value;
    }

    private function positiveInt(mixed $value, string $field): int
    {
        if ($value === null || $value === '') {
            throw new ModelException($field . ' is required.');
        }

        $integer = (int) $value;

        if ($integer <= 0) {
            throw new ModelException($field . ' must be greater than zero.');
        }

        return $integer;
    }

    private function nullableDecimal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->decimal($value);
    }

    private function decimal(mixed $value): string
    {
        if ($value === null || $value === '' || !is_numeric($value) || (float) $value < 0) {
            throw new ModelException('Decimal value must be zero or greater.');
        }

        return number_format((float) $value, 2, '.', '');
    }
}
