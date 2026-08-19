<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Core\Collection;
use App\Core\Database;
use App\Core\ModelException;
use App\Models\NotificationAttempt;
use PDO;

/**
 * Repository for append-only notification delivery attempts.
 */
final class NotificationAttemptRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(NotificationAttempt::class);
    }

    /**
     * Find one attempt by primary key.
     */
    public function findById(int|string $id): NotificationAttempt
    {
        $statement = Database::pdo()->prepare('SELECT * FROM notification_attempts WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new ModelException('Notification attempt not found.');
        }

        return new NotificationAttempt($row);
    }

    /**
     * Append one delivery attempt.
     */
    public function createAttempt(
        int $notificationId,
        string $transportKey,
        string $statusKey,
        ?string $errorCode = null,
        ?string $errorSummary = null
    ): NotificationAttempt {
        $attemptNumber = $this->nextAttemptNumber($notificationId);
        $statement = Database::pdo()->prepare(
            'INSERT INTO notification_attempts (
                notification_id,
                attempt_number,
                transport_key,
                status_key,
                error_code,
                error_summary,
                attempted_at,
                created_at
            ) VALUES (
                :notification_id,
                :attempt_number,
                :transport_key,
                :status_key,
                :error_code,
                :error_summary,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
            )'
        );
        $statement->execute([
            'notification_id' => $notificationId,
            'attempt_number' => $attemptNumber,
            'transport_key' => $this->safeText($transportKey, 50),
            'status_key' => $this->safeText($statusKey, 50),
            'error_code' => $errorCode === null ? null : $this->safeText($errorCode, 100),
            'error_summary' => $errorSummary === null ? null : $this->safeText($errorSummary, 500),
        ]);

        return $this->findById((int) Database::pdo()->lastInsertId());
    }

    /**
     * Count attempts for one notification.
     */
    public function countForNotification(int $notificationId): int
    {
        $statement = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM notification_attempts WHERE notification_id = :notification_id'
        );
        $statement->execute(['notification_id' => $notificationId]);

        return (int) $statement->fetchColumn();
    }

    /**
     * Return attempts for one notification in append order.
     *
     * @return Collection<NotificationAttempt>
     */
    public function findForNotification(int $notificationId): Collection
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM notification_attempts
             WHERE notification_id = :notification_id
             ORDER BY attempt_number ASC, id ASC'
        );
        $statement->execute(['notification_id' => $notificationId]);

        return new Collection(array_map(
            static fn (array $row): NotificationAttempt => new NotificationAttempt($row),
            $statement->fetchAll(PDO::FETCH_ASSOC)
        ));
    }

    private function nextAttemptNumber(int $notificationId): int
    {
        return $this->countForNotification($notificationId) + 1;
    }

    private function safeText(string $value, int $maxLength): string
    {
        return substr(trim($value), 0, $maxLength);
    }
}
