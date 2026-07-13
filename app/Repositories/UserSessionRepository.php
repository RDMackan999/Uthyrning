<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Core\Database;
use App\Models\UserSession;
use PDO;

/**
 * Repository for future server-side user session records.
 */
final class UserSessionRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(UserSession::class);
    }

    /**
     * Find a session by its stored token hash.
     */
    public function findByTokenHash(string $sessionTokenHash): ?UserSession
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM user_sessions WHERE session_token_hash = :token_hash LIMIT 1'
        );
        $statement->execute(['token_hash' => $sessionTokenHash]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : new UserSession($row);
    }

    /**
     * Create a server-side session record for a verified login.
     */
    public function createSession(
        int $userId,
        string $sessionTokenHash,
        ?string $ipAddress,
        ?string $userAgent,
        string $expiresAt
    ): UserSession {
        $statement = Database::pdo()->prepare(
            'INSERT INTO user_sessions (
                user_id,
                session_token_hash,
                ip_address,
                user_agent,
                created_at,
                updated_at,
                last_activity_at,
                expires_at
            ) VALUES (
                :user_id,
                :session_token_hash,
                :ip_address,
                :user_agent,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP(),
                :expires_at
            )'
        );
        $statement->execute([
            'user_id' => $userId,
            'session_token_hash' => $sessionTokenHash,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'expires_at' => $expiresAt,
        ]);

        return $this->findByTokenHash($sessionTokenHash) ?? new UserSession([
            'id' => (int) Database::pdo()->lastInsertId(),
            'user_id' => $userId,
            'session_token_hash' => $sessionTokenHash,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Update last activity for a non-revoked session.
     */
    public function touch(string $sessionTokenHash): bool
    {
        $statement = Database::pdo()->prepare(
            'UPDATE user_sessions
             SET last_activity_at = UTC_TIMESTAMP(),
                 updated_at = UTC_TIMESTAMP()
             WHERE session_token_hash = :token_hash
                AND revoked_at IS NULL'
        );
        $statement->execute(['token_hash' => $sessionTokenHash]);

        return $statement->rowCount() > 0;
    }

    /**
     * Revoke an active session by token hash.
     */
    public function revoke(string $sessionTokenHash): bool
    {
        $statement = Database::pdo()->prepare(
            'UPDATE user_sessions
             SET revoked_at = UTC_TIMESTAMP()
             WHERE session_token_hash = :token_hash
                AND revoked_at IS NULL'
        );
        $statement->execute(['token_hash' => $sessionTokenHash]);

        return $statement->rowCount() > 0;
    }

    /**
     * Revoke all active sessions for a user.
     */
    public function revokeAllForUser(int $userId): int
    {
        $statement = Database::pdo()->prepare(
            'UPDATE user_sessions
             SET revoked_at = UTC_TIMESTAMP()
             WHERE user_id = :user_id
                AND revoked_at IS NULL'
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->rowCount();
    }
}
