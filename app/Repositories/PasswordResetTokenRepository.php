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
}
