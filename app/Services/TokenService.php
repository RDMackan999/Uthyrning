<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EmailVerificationToken;
use App\Models\PasswordResetToken;
use App\Repositories\EmailVerificationTokenRepository;
use App\Repositories\PasswordResetTokenRepository;
use DateTimeImmutable;
use DateTimeZone;
use Exception;

/**
 * Generates and stores hash-only one-time authentication tokens.
 */
final class TokenService
{
    public const DEFAULT_TOKEN_BYTES = 32;
    public const PASSWORD_RESET_TTL_MINUTES = 60;
    public const EMAIL_VERIFICATION_TTL_MINUTES = 1440;

    public function __construct(
        private readonly PasswordResetTokenRepository $passwordResetTokenRepository = new PasswordResetTokenRepository(),
        private readonly EmailVerificationTokenRepository $emailVerificationTokenRepository = new EmailVerificationTokenRepository()
    ) {
    }

    /**
     * Generate a random token. The returned value must only be shown once.
     *
     * @throws Exception
     */
    public function generateToken(int $bytes = self::DEFAULT_TOKEN_BYTES): string
    {
        return bin2hex(random_bytes(max(16, $bytes)));
    }

    /**
     * Hash a token before lookup or storage.
     */
    public function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Create a password reset token record and return the clear token only to the caller.
     *
     * @return array{token: string, token_hash: string, expires_at: string, record: PasswordResetToken}
     *
     * @throws Exception
     */
    public function createPasswordResetToken(int $userId, int $ttlMinutes = self::PASSWORD_RESET_TTL_MINUTES): array
    {
        $token = $this->generateToken();
        $tokenHash = $this->hashToken($token);
        $expiresAt = $this->expiresAt($ttlMinutes);
        $record = $this->passwordResetTokenRepository->createToken($userId, $tokenHash, $expiresAt);

        return [
            'token' => $token,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
            'record' => $record,
        ];
    }

    /**
     * Create an email verification token record and return the clear token only to the caller.
     *
     * @return array{token: string, token_hash: string, expires_at: string, record: EmailVerificationToken}
     *
     * @throws Exception
     */
    public function createEmailVerificationToken(
        int $userId,
        int $ttlMinutes = self::EMAIL_VERIFICATION_TTL_MINUTES
    ): array {
        $token = $this->generateToken();
        $tokenHash = $this->hashToken($token);
        $expiresAt = $this->expiresAt($ttlMinutes);
        $record = $this->emailVerificationTokenRepository->createToken($userId, $tokenHash, $expiresAt);

        return [
            'token' => $token,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
            'record' => $record,
        ];
    }

    /**
     * Find a valid password reset token by its clear token value.
     */
    public function findValidPasswordResetToken(string $token): ?PasswordResetToken
    {
        return $this->passwordResetTokenRepository->findValidByTokenHash($this->hashToken($token));
    }

    /**
     * Mark a password reset token as used.
     */
    public function markPasswordResetTokenUsed(string $token): bool
    {
        return $this->passwordResetTokenRepository->markUsed($this->hashToken($token));
    }

    /**
     * Find a valid email verification token by its clear token value.
     */
    public function findValidEmailVerificationToken(string $token): ?EmailVerificationToken
    {
        return $this->emailVerificationTokenRepository->findValidByTokenHash($this->hashToken($token));
    }

    /**
     * Mark an email verification token as used.
     */
    public function markEmailVerificationTokenUsed(string $token): bool
    {
        return $this->emailVerificationTokenRepository->markUsed($this->hashToken($token));
    }

    /**
     * Return a future UTC timestamp string.
     */
    private function expiresAt(int $ttlMinutes): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify(sprintf('+%d minutes', max(1, $ttlMinutes)))
            ->format('Y-m-d H:i:s');
    }
}
