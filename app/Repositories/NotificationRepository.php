<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Core\Collection;
use App\Core\Database;
use App\Core\ModelException;
use App\Core\NotificationException;
use App\Models\Notification;
use App\Services\PublicIdGenerator;
use PDO;
use PDOException;

/**
 * Repository for notification records and recipient lookup.
 */
final class NotificationRepository extends BaseRepository
{
    private const MAX_PUBLIC_ID_ATTEMPTS = 5;

    /**
     * @var list<string>
     */
    private const RETRYABLE_STATUS_KEYS = ['pending', 'failed'];

    public function __construct(
        private readonly PublicIdGenerator $publicIdGenerator = new PublicIdGenerator()
    ) {
        parent::__construct(Notification::class);
    }

    /**
     * Find one notification by primary key.
     */
    public function findById(int|string $id): Notification
    {
        $statement = Database::pdo()->prepare('SELECT * FROM notifications WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new ModelException('Notification not found.');
        }

        return new Notification($row);
    }

    /**
     * Find an existing notification by idempotency key.
     */
    public function findByIdempotencyKey(string $idempotencyKey): ?Notification
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM notifications WHERE idempotency_key = :idempotency_key LIMIT 1'
        );
        $statement->execute(['idempotency_key' => $idempotencyKey]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : new Notification($row);
    }

    /**
     * Find one non-deleted notification by public id, optionally scoped to one organization.
     */
    public function findByPublicId(string $publicId, ?int $organizationId = null): ?Notification
    {
        $sql = 'SELECT *
                FROM notifications
                WHERE public_id = :public_id';
        $parameters = ['public_id' => $publicId];

        if ($organizationId !== null) {
            $sql .= ' AND organization_id = :organization_id';
            $parameters['organization_id'] = $organizationId;
        }

        $sql .= ' LIMIT 1';

        $statement = Database::pdo()->prepare($sql);
        $statement->execute($parameters);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : new Notification($row);
    }

    /**
     * Return notifications with safe admin display metadata.
     *
     * @return list<array<string, mixed>>
     */
    public function findAllForAdmin(?string $statusKey = null, ?string $eventKey = null, ?array $organizationIds = null): array
    {
        $sql = $this->adminSelectSql() . '
             WHERE 1 = 1';
        $parameters = [];

        if ($statusKey !== null) {
            $sql .= ' AND notifications.status_key = :status_key';
            $parameters['status_key'] = $statusKey;
        }

        if ($eventKey !== null) {
            $sql .= ' AND notifications.event_key = :event_key';
            $parameters['event_key'] = $eventKey;
        }

        $sql .= ' ' . $this->organizationScopeSql($organizationIds, 'notifications.organization_id', $parameters);

        $sql .= ' ORDER BY notifications.created_at DESC, notifications.id DESC';

        $statement = Database::pdo()->prepare($sql);
        $statement->execute($parameters);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }

    /**
     * Find one notification with safe admin display metadata.
     *
     * @return array<string, mixed>|null
     */
    public function findAdminByPublicId(string $publicId, ?int $organizationId = null): ?array
    {
        $sql = $this->adminSelectSql() . '
             WHERE notifications.public_id = :public_id';
        $parameters = ['public_id' => $publicId];

        if ($organizationId !== null) {
            $sql .= ' AND notifications.organization_id = :organization_id';
            $parameters['organization_id'] = $organizationId;
        }

        $sql .= ' LIMIT 1';

        $statement = Database::pdo()->prepare($sql);
        $statement->execute($parameters);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * Determine whether a notification can be retried without creating a new logical record.
     */
    public function isRetryable(Notification $notification): bool
    {
        $data = $notification->toArray();
        $statusKey = (string) ($data['status_key'] ?? '');
        $attemptsCount = (int) ($data['attempts_count'] ?? 0);
        $maxAttempts = (int) ($data['max_attempts'] ?? 3);

        return in_array($statusKey, self::RETRYABLE_STATUS_KEYS, true)
            && $attemptsCount < $maxAttempts;
    }

    /**
     * Create a notification once, or return the existing logical notification.
     *
     * @param array<string, mixed> $data
     * @return array{notification: Notification, created: bool}
     */
    public function createIdempotent(array $data): array
    {
        $idempotencyKey = $this->requiredString($data['idempotency_key'] ?? null, 'idempotency_key');
        $existing = $this->findByIdempotencyKey($idempotencyKey);

        if ($existing !== null) {
            return ['notification' => $existing, 'created' => false];
        }

        try {
            $statement = Database::pdo()->prepare(
                'INSERT INTO notifications (
                    public_id,
                    organization_id,
                    booking_id,
                    event_key,
                    channel_key,
                    recipient_type,
                    recipient_email,
                    recipient_email_normalized,
                    template_key,
                    subject,
                    status_key,
                    idempotency_key,
                    attempts_count,
                    max_attempts,
                    scheduled_at,
                    created_at,
                    updated_at
                ) VALUES (
                    :public_id,
                    :organization_id,
                    :booking_id,
                    :event_key,
                    :channel_key,
                    :recipient_type,
                    :recipient_email,
                    :recipient_email_normalized,
                    :template_key,
                    :subject,
                    :status_key,
                    :idempotency_key,
                    0,
                    :max_attempts,
                    :scheduled_at,
                    UTC_TIMESTAMP(),
                    UTC_TIMESTAMP()
                )'
            );
            $recipientEmail = $this->normalizeEmail($this->requiredString($data['recipient_email'] ?? null, 'recipient_email'));
            $statement->execute([
                'public_id' => $this->generateUniquePublicId(),
                'organization_id' => $this->requiredInt($data['organization_id'] ?? null, 'organization_id'),
                'booking_id' => $this->nullableInt($data['booking_id'] ?? null),
                'event_key' => $this->requiredString($data['event_key'] ?? null, 'event_key'),
                'channel_key' => $this->requiredString($data['channel_key'] ?? 'email', 'channel_key'),
                'recipient_type' => $this->requiredString($data['recipient_type'] ?? null, 'recipient_type'),
                'recipient_email' => $recipientEmail,
                'recipient_email_normalized' => $recipientEmail,
                'template_key' => $this->requiredString($data['template_key'] ?? null, 'template_key'),
                'subject' => $this->sanitizeHeader($this->requiredString($data['subject'] ?? null, 'subject')),
                'status_key' => $this->requiredString($data['status_key'] ?? 'pending', 'status_key'),
                'idempotency_key' => $idempotencyKey,
                'max_attempts' => $this->safeMaxAttempts($data['max_attempts'] ?? 3),
                'scheduled_at' => $this->nullableString($data['scheduled_at'] ?? null),
            ]);
        } catch (PDOException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }
        }

        $notification = $this->findByIdempotencyKey($idempotencyKey);
        if ($notification === null) {
            throw new ModelException('Notification could not be created.');
        }

        return ['notification' => $notification, 'created' => true];
    }

    /**
     * Mark a notification as successfully delivered.
     */
    public function markSent(int $notificationId, int $attemptsCount): Notification
    {
        $statement = Database::pdo()->prepare(
            'UPDATE notifications
             SET status_key = :status_key,
                attempts_count = :attempts_count,
                last_error_code = NULL,
                last_error_summary = NULL,
                sent_at = UTC_TIMESTAMP(),
                failed_at = NULL,
                updated_at = UTC_TIMESTAMP()
             WHERE id = :id'
        );
        $statement->execute([
            'id' => $notificationId,
            'status_key' => 'sent',
            'attempts_count' => $attemptsCount,
        ]);

        return $this->findById($notificationId);
    }

    /**
     * Record a safe failure summary without storing provider secrets or body.
     */
    public function markFailed(
        int $notificationId,
        int $attemptsCount,
        string $errorCode,
        string $errorSummary,
        bool $canRetry
    ): Notification {
        $statement = Database::pdo()->prepare(
            'UPDATE notifications
             SET status_key = :status_key,
                attempts_count = :attempts_count,
                last_error_code = :last_error_code,
                last_error_summary = :last_error_summary,
                failed_at = CASE WHEN :failed_status = 1 THEN UTC_TIMESTAMP() ELSE failed_at END,
                updated_at = UTC_TIMESTAMP()
             WHERE id = :id'
        );
        $statement->execute([
            'id' => $notificationId,
            'status_key' => $canRetry ? 'pending' : 'failed',
            'attempts_count' => $attemptsCount,
            'last_error_code' => $this->truncateSafe($errorCode, 100),
            'last_error_summary' => $this->truncateSafe($errorSummary, 500),
            'failed_status' => $canRetry ? 0 : 1,
        ]);

        return $this->findById($notificationId);
    }

    /**
     * Return public-safe booking notification context.
     *
     * @return array<string, mixed>
     */
    public function bookingContext(int $organizationId, int $bookingId): array
    {
        $statement = Database::pdo()->prepare(
            'SELECT bookings.id,
                bookings.public_id,
                bookings.organization_id,
                bookings.status_key,
                bookings.start_date,
                bookings.end_date,
                bookings.currency,
                bookings.total_units,
                bookings.subtotal_amount,
                bookings.deposit_amount,
                organizations.name AS organization_name,
                organizations.email AS organization_email,
                booking_customer_snapshots.customer_name,
                booking_customer_snapshots.customer_email,
                booking_customer_snapshots.customer_email_normalized,
                booking_items.rental_item_id,
                rental_items.name AS rental_item_name
             FROM bookings
             INNER JOIN organizations
                ON organizations.id = bookings.organization_id
             LEFT JOIN booking_customer_snapshots
                ON booking_customer_snapshots.booking_id = bookings.id
             LEFT JOIN booking_items
                ON booking_items.booking_id = bookings.id
             LEFT JOIN rental_items
                ON rental_items.id = booking_items.rental_item_id
             WHERE bookings.id = :booking_id
                AND bookings.organization_id = :organization_id
                AND bookings.deleted_at IS NULL
             ORDER BY booking_items.id ASC
             LIMIT 1'
        );
        $statement->execute([
            'organization_id' => $organizationId,
            'booking_id' => $bookingId,
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new ModelException('Booking notification context not found.');
        }

        return $row;
    }

    /**
     * Resolve the first safe organization recipient for admin/renter notifications.
     */
    public function findOrganizationNotificationEmail(int $organizationId): ?string
    {
        $statement = Database::pdo()->prepare(
            'SELECT email
             FROM organizations
             WHERE id = :organization_id
                AND status_key = :status_key
                AND deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute([
            'organization_id' => $organizationId,
            'status_key' => 'active',
        ]);

        $email = $this->nullableString($statement->fetchColumn());

        if ($email !== null && $this->isValidEmail($email)) {
            return $this->normalizeEmail($email);
        }

        $statement = Database::pdo()->prepare(
            'SELECT users.email_normalized
             FROM user_roles
             INNER JOIN roles
                ON roles.id = user_roles.role_id
             INNER JOIN users
                ON users.id = user_roles.user_id
             WHERE user_roles.organization_id = :organization_id
                AND roles.role_key IN (:admin_role, :owner_role, :staff_role)
                AND roles.status_key = :role_status_key
                AND roles.deleted_at IS NULL
                AND users.status_key = :user_status_key
                AND users.deleted_at IS NULL
             ORDER BY roles.role_key ASC, users.id ASC
             LIMIT 1'
        );
        $statement->execute([
            'organization_id' => $organizationId,
            'admin_role' => 'organization_admin',
            'owner_role' => 'organization_owner',
            'staff_role' => 'organization_staff',
            'role_status_key' => 'active',
            'user_status_key' => 'active',
        ]);

        $email = $this->nullableString($statement->fetchColumn());

        return $email !== null && $this->isValidEmail($email) ? $this->normalizeEmail($email) : null;
    }

    /**
     * Find notifications for a booking event and recipient.
     *
     * @return Collection<Notification>
     */
    public function findForBookingEvent(int $bookingId, string $eventKey): Collection
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM notifications
             WHERE booking_id = :booking_id
                AND event_key = :event_key
             ORDER BY id ASC'
        );
        $statement->execute([
            'booking_id' => $bookingId,
            'event_key' => $eventKey,
        ]);

        return new Collection(array_map(
            static fn (array $row): Notification => new Notification($row),
            $statement->fetchAll(PDO::FETCH_ASSOC)
        ));
    }

    /**
     * Return supported admin filter options.
     *
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            'pending' => 'Väntar',
            'sent' => 'Skickad',
            'failed' => 'Misslyckad',
            'cancelled' => 'Avbruten',
        ];
    }

    /**
     * Return supported Version 1 notification event filters.
     *
     * @return array<string, string>
     */
    public static function eventOptions(): array
    {
        return [
            'booking_created' => 'Ny bokningsförfrågan',
            'booking_approved' => 'Bokning godkänd',
            'booking_rejected' => 'Bokning nekad',
            'booking_cancelled' => 'Bokning avbokad',
        ];
    }

    private function adminSelectSql(): string
    {
        return 'SELECT notifications.public_id,
                notifications.organization_id,
                notifications.booking_id,
                notifications.event_key,
                notifications.channel_key,
                notifications.recipient_type,
                notifications.recipient_email,
                notifications.template_key,
                notifications.subject,
                notifications.status_key,
                notifications.idempotency_key,
                notifications.attempts_count,
                notifications.max_attempts,
                notifications.last_error_code,
                notifications.last_error_summary,
                notifications.scheduled_at,
                notifications.sent_at,
                notifications.failed_at,
                notifications.created_at,
                notifications.updated_at,
                organizations.name AS organization_name,
                bookings.public_id AS booking_public_id
             FROM notifications
             INNER JOIN organizations
                ON organizations.id = notifications.organization_id
             LEFT JOIN bookings
                ON bookings.id = notifications.booking_id';
    }

    private function generateUniquePublicId(): string
    {
        for ($attempt = 0; $attempt < self::MAX_PUBLIC_ID_ATTEMPTS; $attempt++) {
            $publicId = $this->publicIdGenerator->generate('ntf');

            if ($this->publicIdExists($publicId)) {
                continue;
            }

            return $publicId;
        }

        throw new ModelException('Could not generate unique notification public id.');
    }

    private function publicIdExists(string $publicId): bool
    {
        $statement = Database::pdo()->prepare('SELECT 1 FROM notifications WHERE public_id = :public_id LIMIT 1');
        $statement->execute(['public_id' => $publicId]);

        return $statement->fetchColumn() !== false;
    }

    private function normalizeEmail(string $email): string
    {
        $normalized = strtolower(trim($email));

        if (!$this->isValidEmail($normalized)) {
            throw NotificationException::invalidRecipient();
        }

        return $normalized;
    }

    private function isValidEmail(string $email): bool
    {
        return !str_contains($email, "\r")
            && !str_contains($email, "\n")
            && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function sanitizeHeader(string $value): string
    {
        if (str_contains($value, "\r") || str_contains($value, "\n")) {
            throw new NotificationException('Notification header value is invalid.');
        }

        return $this->truncateSafe($value, 255);
    }

    private function truncateSafe(string $value, int $maxLength): string
    {
        return substr(trim($value), 0, $maxLength);
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
        if ($value === false || $value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
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

    private function safeMaxAttempts(mixed $value): int
    {
        $maxAttempts = (int) $value;

        return max(1, min(3, $maxAttempts));
    }

    /**
     * @param list<int>|null $organizationIds
     * @param array<string, mixed> $params
     */
    private function organizationScopeSql(?array $organizationIds, string $column, array &$params): string
    {
        if ($organizationIds === null) {
            return '';
        }

        $ids = array_values(array_filter(
            array_unique(array_map(static fn (mixed $id): int => (int) $id, $organizationIds)),
            static fn (int $id): bool => $id > 0
        ));

        if ($ids === []) {
            return 'AND 1 = 0';
        }

        $placeholders = [];

        foreach ($ids as $index => $organizationId) {
            $name = 'scope_organization_id_' . $index;
            $placeholders[] = ':' . $name;
            $params[$name] = $organizationId;
        }

        return 'AND ' . $column . ' IN (' . implode(', ', $placeholders) . ')';
    }
}
