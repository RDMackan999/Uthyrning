<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Core\Collection;
use App\Core\Database;
use App\Core\ModelException;
use App\Models\Booking;
use App\Services\PublicIdGenerator;
use PDO;
use RuntimeException;

/**
 * Repository for booking header foundation records.
 */
final class BookingRepository extends BaseRepository
{
    private const MAX_PUBLIC_ID_ATTEMPTS = 5;

    /**
     * @var list<string>
     */
    private const STATUS_KEYS = ['request', 'approved', 'rejected', 'cancelled', 'active', 'completed'];

    public function __construct(
        private readonly PublicIdGenerator $publicIdGenerator = new PublicIdGenerator()
    ) {
        parent::__construct(Booking::class);
    }

    /**
     * Find a non-deleted booking by primary key, optionally scoped by organization.
     */
    public function findById(int|string $id, ?int $organizationId = null): Booking
    {
        $sql = 'SELECT * FROM bookings WHERE id = :id AND deleted_at IS NULL';
        $params = ['id' => $id];

        if ($organizationId !== null) {
            $sql .= ' AND organization_id = :organization_id';
            $params['organization_id'] = $organizationId;
        }

        $statement = Database::pdo()->prepare($sql . ' LIMIT 1');
        $statement->execute($params);

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new ModelException('Booking not found.');
        }

        return new Booking($row);
    }

    /**
     * Find a non-deleted booking by immutable public id.
     */
    public function findByPublicId(string $publicId, ?int $organizationId = null): ?Booking
    {
        $sql = 'SELECT * FROM bookings WHERE public_id = :public_id AND deleted_at IS NULL';
        $params = ['public_id' => trim($publicId)];

        if ($organizationId !== null) {
            $sql .= ' AND organization_id = :organization_id';
            $params['organization_id'] = $organizationId;
        }

        $statement = Database::pdo()->prepare($sql . ' LIMIT 1');
        $statement->execute($params);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : new Booking($row);
    }

    /**
     * Find public-safe booking fields by immutable public id.
     */
    public function findPublicByPublicId(string $publicId): ?Booking
    {
        $statement = Database::pdo()->prepare(
            'SELECT public_id,
                status_key,
                start_date,
                end_date,
                customer_comment,
                currency,
                total_units,
                subtotal_amount,
                deposit_amount,
                created_at,
                updated_at
             FROM bookings
             WHERE public_id = :public_id
                AND deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute(['public_id' => trim($publicId)]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : new Booking($row);
    }

    /**
     * Find non-deleted bookings for one organization.
     */
    public function findForOrganization(int $organizationId): Collection
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM bookings
             WHERE organization_id = :organization_id
                AND deleted_at IS NULL
             ORDER BY created_at DESC, id DESC'
        );
        $statement->execute(['organization_id' => $organizationId]);

        return $this->bookingsFromRows($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Create a booking header and optional customer snapshot.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): Booking
    {
        $organizationId = $this->requiredInt($data['organization_id'] ?? null, 'organization_id');
        $statusKey = $this->normalizeStatus((string) ($data['status_key'] ?? 'request'));
        $startDate = $this->normalizeDate((string) ($data['start_date'] ?? ''), 'start_date');
        $endDate = $this->normalizeDate((string) ($data['end_date'] ?? ''), 'end_date');
        $this->ensureValidDateRange($startDate, $endDate);
        $this->ensureStatusExists($statusKey);

        $statement = Database::pdo()->prepare(
            'INSERT INTO bookings (
                public_id,
                organization_id,
                customer_id,
                company_id,
                status_key,
                start_date,
                end_date,
                customer_comment,
                internal_note,
                currency,
                total_units,
                subtotal_amount,
                deposit_amount,
                created_at,
                updated_at
            ) VALUES (
                :public_id,
                :organization_id,
                :customer_id,
                :company_id,
                :status_key,
                :start_date,
                :end_date,
                :customer_comment,
                :internal_note,
                :currency,
                :total_units,
                :subtotal_amount,
                :deposit_amount,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
            )'
        );
        $statement->execute([
            'public_id' => $this->generateUniquePublicId(),
            'organization_id' => $organizationId,
            'customer_id' => $this->nullableInt($data['customer_id'] ?? null),
            'company_id' => $this->nullableInt($data['company_id'] ?? null),
            'status_key' => $statusKey,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'customer_comment' => $this->nullableString($data['customer_comment'] ?? null),
            'internal_note' => $this->nullableString($data['internal_note'] ?? null),
            'currency' => $this->normalizeCurrency((string) ($data['currency'] ?? 'SEK')),
            'total_units' => (int) ($data['total_units'] ?? 0),
            'subtotal_amount' => $this->decimal($data['subtotal_amount'] ?? '0.00', false),
            'deposit_amount' => $this->nullableDecimal($data['deposit_amount'] ?? null),
        ]);

        $bookingId = (int) Database::pdo()->lastInsertId();
        $this->createCustomerSnapshot($bookingId, $data);
        $this->recordStatusHistory($bookingId, null, $statusKey, $this->nullableInt($data['changed_by_user_id'] ?? null));

        return $this->findById($bookingId, $organizationId);
    }

    /**
     * Persist a status key and append status history without enforcing transition rules.
     */
    public function updateStatus(
        int $organizationId,
        int|string $id,
        string $statusKey,
        ?int $changedByUserId = null,
        ?string $comment = null
    ): Booking {
        $current = $this->findById($id, $organizationId);
        $currentData = $current->toArray();
        $fromStatusKey = (string) ($currentData['status_key'] ?? '');
        $toStatusKey = $this->normalizeStatus($statusKey);
        $this->ensureStatusExists($toStatusKey);

        $statement = Database::pdo()->prepare(
            'UPDATE bookings
             SET status_key = :status_key,
                updated_at = UTC_TIMESTAMP()
             WHERE id = :id
                AND organization_id = :organization_id
                AND deleted_at IS NULL'
        );
        $statement->execute([
            'id' => $id,
            'organization_id' => $organizationId,
            'status_key' => $toStatusKey,
        ]);

        $this->recordStatusHistory($id, $fromStatusKey, $toStatusKey, $changedByUserId, $comment);

        return $this->findById($id, $organizationId);
    }

    /**
     * Soft delete a booking without removing historical rows.
     */
    public function delete(int|string $id, ?int $organizationId = null): bool
    {
        $sql = 'UPDATE bookings
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
     * @param list<array<string, mixed>> $rows
     * @return Collection<Booking>
     */
    private function bookingsFromRows(array $rows): Collection
    {
        return new Collection(array_map(
            static fn (array $row): Booking => new Booking($row),
            $rows
        ));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createCustomerSnapshot(int $bookingId, array $data): void
    {
        $hasSnapshot = array_key_exists('customer_name', $data)
            || array_key_exists('customer_email', $data)
            || array_key_exists('customer_phone', $data);

        if (!$hasSnapshot) {
            return;
        }

        $customerName = trim((string) ($data['customer_name'] ?? ''));
        $customerEmail = trim((string) ($data['customer_email'] ?? ''));
        $customerPhone = trim((string) ($data['customer_phone'] ?? ''));

        if ($customerName === '' || $customerEmail === '' || $customerPhone === '') {
            throw new ModelException('Customer snapshot requires name, email and phone.');
        }

        $statement = Database::pdo()->prepare(
            'INSERT INTO booking_customer_snapshots (
                booking_id,
                customer_id,
                company_id,
                customer_name,
                customer_email,
                customer_email_normalized,
                customer_phone,
                company_name,
                created_at
            ) VALUES (
                :booking_id,
                :customer_id,
                :company_id,
                :customer_name,
                :customer_email,
                :customer_email_normalized,
                :customer_phone,
                :company_name,
                UTC_TIMESTAMP()
            )'
        );
        $statement->execute([
            'booking_id' => $bookingId,
            'customer_id' => $this->nullableInt($data['customer_id'] ?? null),
            'company_id' => $this->nullableInt($data['company_id'] ?? null),
            'customer_name' => $customerName,
            'customer_email' => $customerEmail,
            'customer_email_normalized' => $this->normalizeEmail($customerEmail),
            'customer_phone' => $customerPhone,
            'company_name' => $this->nullableString($data['company_name'] ?? null),
        ]);
    }

    private function recordStatusHistory(
        int|string $bookingId,
        ?string $fromStatusKey,
        string $toStatusKey,
        ?int $changedByUserId = null,
        ?string $comment = null
    ): void {
        $statement = Database::pdo()->prepare(
            'INSERT INTO booking_status_history (
                booking_id,
                from_status_key,
                to_status_key,
                changed_by_user_id,
                comment,
                created_at
            ) VALUES (
                :booking_id,
                :from_status_key,
                :to_status_key,
                :changed_by_user_id,
                :comment,
                UTC_TIMESTAMP()
            )'
        );
        $statement->execute([
            'booking_id' => $bookingId,
            'from_status_key' => $fromStatusKey,
            'to_status_key' => $toStatusKey,
            'changed_by_user_id' => $changedByUserId,
            'comment' => $this->nullableString($comment),
        ]);
    }

    private function generateUniquePublicId(): string
    {
        for ($attempt = 0; $attempt < self::MAX_PUBLIC_ID_ATTEMPTS; $attempt++) {
            $publicId = $this->publicIdGenerator->generate('bkg');

            if ($this->findByPublicId($publicId) === null) {
                return $publicId;
            }
        }

        throw new RuntimeException('Could not generate unique booking public id.');
    }

    private function ensureStatusExists(string $statusKey): void
    {
        $statement = Database::pdo()->prepare(
            'SELECT status_key
             FROM booking_statuses
             WHERE status_key = :status_key
                AND is_active = 1
                AND deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute(['status_key' => $statusKey]);

        if ($statement->fetchColumn() === false) {
            throw new ModelException('Booking status is not available.');
        }
    }

    private function normalizeStatus(string $statusKey): string
    {
        $normalized = strtolower(trim($statusKey));

        if (!in_array($normalized, self::STATUS_KEYS, true)) {
            throw new ModelException('Booking status is not supported in Version 1.');
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
            throw new ModelException('Booking start_date must be before or equal to end_date.');
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

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
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

    private function nullableDecimal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->decimal($value, false);
    }

    private function decimal(mixed $value, bool $mustBePositive = true): string
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            throw new ModelException('Decimal value is required.');
        }

        if ($mustBePositive && (float) $value <= 0) {
            throw new ModelException('Decimal value must be greater than zero.');
        }

        if (!$mustBePositive && (float) $value < 0) {
            throw new ModelException('Decimal value cannot be negative.');
        }

        return number_format((float) $value, 2, '.', '');
    }
}
