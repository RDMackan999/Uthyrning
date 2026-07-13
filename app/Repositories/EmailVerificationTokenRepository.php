<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Core\Database;
use App\Models\EmailVerificationToken;
use PDO;

/**
 * Repository for future email verification token records.
 */
final class EmailVerificationTokenRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(EmailVerificationToken::class);
    }

    /**
     * Create a hash-only email verification token record.
     */
    public function createToken(int $userId, string $tokenHash, string $expiresAt): EmailVerificationToken
    {
        $statement = Database::pdo()->prepare(
            'INSERT INTO email_verification_tokens (
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

        return new EmailVerificationToken([
            'id' => (int) Database::pdo()->lastInsertId(),
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Find an unused, non-expired email verification token by hash.
     */
    public function findValidByTokenHash(string $tokenHash): ?EmailVerificationToken
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM email_verification_tokens
             WHERE token_hash = :token_hash
                AND used_at IS NULL
                AND expires_at > UTC_TIMESTAMP()
             LIMIT 1'
        );
        $statement->execute(['token_hash' => $tokenHash]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : new EmailVerificationToken($row);
    }

    /**
     * Mark an email verification token as used.
     */
    public function markUsed(string $tokenHash): bool
    {
        $statement = Database::pdo()->prepare(
            'UPDATE email_verification_tokens
             SET used_at = UTC_TIMESTAMP(),
                 updated_at = UTC_TIMESTAMP()
             WHERE token_hash = :token_hash
                AND used_at IS NULL'
        );
        $statement->execute(['token_hash' => $tokenHash]);

        return $statement->rowCount() > 0;
    }
}
