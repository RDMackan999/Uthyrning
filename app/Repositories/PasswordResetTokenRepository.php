<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Core\Database;
use App\Models\PasswordResetToken;
use PDO;

/**
 * Repository for future password reset token records.
 */
final class PasswordResetTokenRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(PasswordResetToken::class);
    }

    /**
     * Create a hash-only password reset token record.
     */
    public function createToken(int $userId, string $tokenHash, string $expiresAt): PasswordResetToken
    {
        $statement = Database::pdo()->prepare(
            'INSERT INTO password_reset_tokens (
                user_id,
                token_hash,
                created_at,
                updated_at,
                expires_at
            ) VALUES (
                :user_id,
                :token_hash,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP(),
                :expires_at
            )'
        );
        $statement->execute([
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
        ]);

        return new PasswordResetToken([
            'id' => (int) Database::pdo()->lastInsertId(),
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Find an unused, non-expired password reset token by hash.
     */
    public function findValidByTokenHash(string $tokenHash): ?PasswordResetToken
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM password_reset_tokens
             WHERE token_hash = :token_hash
                AND used_at IS NULL
                AND expires_at > UTC_TIMESTAMP()
             LIMIT 1'
        );
        $statement->execute(['token_hash' => $tokenHash]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : new PasswordResetToken($row);
    }

    /**
     * Mark a password reset token as used.
     */
    public function markUsed(string $tokenHash): bool
    {
        $statement = Database::pdo()->prepare(
            'UPDATE password_reset_tokens
             SET used_at = UTC_TIMESTAMP(),
                 updated_at = UTC_TIMESTAMP()
             WHERE token_hash = :token_hash
                AND used_at IS NULL'
        );
        $statement->execute(['token_hash' => $tokenHash]);

        return $statement->rowCount() > 0;
    }
}
