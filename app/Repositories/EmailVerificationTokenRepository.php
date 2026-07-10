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
}
