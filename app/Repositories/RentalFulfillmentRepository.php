<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Core\Database;
use App\Core\ModelException;
use App\Models\RentalFulfillment;
use App\Services\PublicIdGenerator;
use PDO;

/**
 * Repository for rental fulfillment headers.
 */
final class RentalFulfillmentRepository extends BaseRepository
{
    private const MAX_PUBLIC_ID_ATTEMPTS = 5;

    public function __construct(
        private readonly PublicIdGenerator $publicIdGenerator = new PublicIdGenerator()
    ) {
        parent::__construct(RentalFulfillment::class);
    }

    /**
     * Find a fulfillment by primary key.
     */
    public function findById(int|string $id, ?int $organizationId = null): RentalFulfillment
    {
        $sql = 'SELECT * FROM rental_fulfillments WHERE id = :id AND deleted_at IS NULL';
        $params = ['id' => $id];

        if ($organizationId !== null) {
            $sql .= ' AND organization_id = :organization_id';
            $params['organization_id'] = $organizationId;
        }

        $sql .= ' LIMIT 1';
        $statement = Database::pdo()->prepare($sql);
        $statement->execute($params);

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new ModelException('Rental fulfillment not found.');
        }

        return new RentalFulfillment($row);
    }

    /**
     * Find a fulfillment by booking id.
     */
    public function findByBookingId(int $bookingId, ?int $organizationId = null): ?RentalFulfillment
    {
        $sql = 'SELECT * FROM rental_fulfillments WHERE booking_id = :booking_id AND deleted_at IS NULL';
        $params = ['booking_id' => $bookingId];

        if ($organizationId !== null) {
            $sql .= ' AND organization_id = :organization_id';
            $params['organization_id'] = $organizationId;
        }

        $sql .= ' LIMIT 1';
        $statement = Database::pdo()->prepare($sql);
        $statement->execute($params);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : new RentalFulfillment($row);
    }

    /**
     * Create a fulfillment header without changing booking status.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): RentalFulfillment
    {
        $organizationId = $this->requiredInt($data['organization_id'] ?? null, 'organization_id');
        $bookingId = $this->requiredInt($data['booking_id'] ?? null, 'booking_id');

        $statement = Database::pdo()->prepare(
            'INSERT INTO rental_fulfillments (
                public_id,
                organization_id,
                booking_id,
                planned_start_date,
                planned_end_date,
                actual_handover_at,
                handed_over_by_user_id,
                received_by_name,
                handover_note,
                terms_version_key,
                deposit_required_amount,
                deposit_received_amount,
                deposit_status_key,
                created_at,
                updated_at
            ) VALUES (
                :public_id,
                :organization_id,
                :booking_id,
                :planned_start_date,
                :planned_end_date,
                :actual_handover_at,
                :handed_over_by_user_id,
                :received_by_name,
                :handover_note,
                :terms_version_key,
                :deposit_required_amount,
                :deposit_received_amount,
                :deposit_status_key,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
            )'
        );
        $statement->execute([
            'public_id' => $this->generateUniquePublicId(),
            'organization_id' => $organizationId,
            'booking_id' => $bookingId,
            'planned_start_date' => $this->requiredDate($data['planned_start_date'] ?? null, 'planned_start_date'),
            'planned_end_date' => $this->requiredDate($data['planned_end_date'] ?? null, 'planned_end_date'),
            'actual_handover_at' => $this->requiredDateTime($data['actual_handover_at'] ?? null, 'actual_handover_at'),
            'handed_over_by_user_id' => $this->nullableInt($data['handed_over_by_user_id'] ?? null),
            'received_by_name' => $this->nullableString($data['received_by_name'] ?? null),
            'handover_note' => $this->nullableString($data['handover_note'] ?? null),
            'terms_version_key' => $this->nullableString($data['terms_version_key'] ?? null),
            'deposit_required_amount' => $this->nullableDecimal($data['deposit_required_amount'] ?? null),
            'deposit_received_amount' => $this->nullableDecimal($data['deposit_received_amount'] ?? null),
            'deposit_status_key' => $this->normalizeKey((string) ($data['deposit_status_key'] ?? 'not_required')),
        ]);

        return $this->findById((int) Database::pdo()->lastInsertId(), $organizationId);
    }

    /**
     * Mark an existing fulfillment as returned.
     *
     * @param array<string, mixed> $data
     */
    public function markReturned(int $id, int $organizationId, array $data): RentalFulfillment
    {
        $statement = Database::pdo()->prepare(
            'UPDATE rental_fulfillments
             SET actual_return_at = :actual_return_at,
                returned_to_user_id = :returned_to_user_id,
                return_note = :return_note,
                deposit_returned_amount = :deposit_returned_amount,
                deposit_retained_amount = :deposit_retained_amount,
                deposit_status_key = :deposit_status_key,
                updated_at = UTC_TIMESTAMP()
             WHERE id = :id
                AND organization_id = :organization_id
                AND actual_return_at IS NULL
                AND deleted_at IS NULL'
        );
        $statement->execute([
            'id' => $id,
            'organization_id' => $organizationId,
            'actual_return_at' => $this->requiredDateTime($data['actual_return_at'] ?? null, 'actual_return_at'),
            'returned_to_user_id' => $this->nullableInt($data['returned_to_user_id'] ?? null),
            'return_note' => $this->nullableString($data['return_note'] ?? null),
            'deposit_returned_amount' => $this->nullableDecimal($data['deposit_returned_amount'] ?? null),
            'deposit_retained_amount' => $this->nullableDecimal($data['deposit_retained_amount'] ?? null),
            'deposit_status_key' => $this->normalizeKey((string) ($data['deposit_status_key'] ?? 'not_required')),
        ]);

        if ($statement->rowCount() !== 1) {
            throw new ModelException('Rental fulfillment return could not be recorded.');
        }

        return $this->findById($id, $organizationId);
    }

    private function generateUniquePublicId(): string
    {
        for ($attempt = 0; $attempt < self::MAX_PUBLIC_ID_ATTEMPTS; $attempt++) {
            $publicId = $this->publicIdGenerator->generate('ful');

            $statement = Database::pdo()->prepare(
                'SELECT id FROM rental_fulfillments WHERE public_id = :public_id LIMIT 1'
            );
            $statement->execute(['public_id' => $publicId]);

            if ($statement->fetchColumn() === false) {
                return $publicId;
            }
        }

        throw new ModelException('Could not generate unique rental fulfillment public_id.');
    }

    private function requiredDate(mixed $value, string $field): string
    {
        $date = trim((string) $value);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new ModelException($field . ' must be a YYYY-MM-DD date.');
        }

        return $date;
    }

    private function requiredDateTime(mixed $value, string $field): string
    {
        $dateTime = trim((string) $value);

        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $dateTime)) {
            throw new ModelException($field . ' must be a UTC datetime string.');
        }

        return $dateTime;
    }

    private function requiredInt(mixed $value, string $field): int
    {
        if ($value === null || $value === '') {
            throw new ModelException($field . ' is required.');
        }

        return (int) $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
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

    private function normalizeKey(string $key): string
    {
        return strtolower(trim($key));
    }
}
