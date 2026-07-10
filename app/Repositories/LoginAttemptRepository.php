<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Core\Database;
use App\Models\LoginAttempt;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Repository for future login attempt tracking and rate limiting.
 */
final class LoginAttemptRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(LoginAttempt::class);
    }

    /**
     * Record one login attempt without deciding authentication outcome.
     */
    public function recordAttempt(
        string $normalizedIdentifier,
        ?int $userId,
        string $ipAddress,
        bool $wasSuccessful
    ): LoginAttempt {
        $statement = Database::pdo()->prepare(
            'INSERT INTO login_attempts (
                normalized_identifier,
                user_id,
                ip_address,
                was_successful,
                attempted_at
            ) VALUES (
                :normalized_identifier,
                :user_id,
                :ip_address,
                :was_successful,
                UTC_TIMESTAMP()
            )'
        );
        $statement->execute([
            'normalized_identifier' => $normalizedIdentifier,
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'was_successful' => $wasSuccessful ? 1 : 0,
        ]);

        return new LoginAttempt([
            'id' => (int) Database::pdo()->lastInsertId(),
            'normalized_identifier' => $normalizedIdentifier,
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'was_successful' => $wasSuccessful ? 1 : 0,
            'attempted_at' => $this->currentUtcTimestamp(),
        ]);
    }

    /**
     * Count recent failed attempts for one normalized identifier.
     */
    public function countRecentFailuresByIdentifier(string $normalizedIdentifier, int $withinMinutes = 15): int
    {
        $statement = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE normalized_identifier = :normalized_identifier
                AND was_successful = 0
                AND attempted_at >= :since'
        );
        $statement->execute([
            'normalized_identifier' => $normalizedIdentifier,
            'since' => $this->sinceTimestamp($withinMinutes),
        ]);

        return (int) $statement->fetchColumn();
    }

    /**
     * Count recent failed attempts for one IP address.
     */
    public function countRecentFailuresByIp(string $ipAddress, int $withinMinutes = 15): int
    {
        $statement = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE ip_address = :ip_address
                AND was_successful = 0
                AND attempted_at >= :since'
        );
        $statement->execute([
            'ip_address' => $ipAddress,
            'since' => $this->sinceTimestamp($withinMinutes),
        ]);

        return (int) $statement->fetchColumn();
    }

    /**
     * Return a UTC timestamp string for the start of a rate-limit window.
     */
    private function sinceTimestamp(int $withinMinutes): string
    {
        $safeMinutes = max(1, $withinMinutes);

        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify(sprintf('-%d minutes', $safeMinutes))
            ->format('Y-m-d H:i:s');
    }

    /**
     * Return the current UTC timestamp for the model returned after insert.
     */
    private function currentUtcTimestamp(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }
}
