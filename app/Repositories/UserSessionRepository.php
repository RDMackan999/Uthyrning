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
}
