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
        private readonly EmailVerificationTokenRepository $emailVerificationTokenRepository = new EmailVerificationTokenRepository(),
        private readonly AuditService $auditService = new AuditService()
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
        $this->auditService->record(
            'password_reset_token_created',
            null,
            'users',
            $userId,
            null,
            null,
            [
                'result' => 'created',
                'token_record_id' => $this->modelId($record),
                'ttl_minutes' => max(1, $ttlMinutes),
            ]
        );

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
        $this->auditService->record(
            'email_verification_token_created',
            null,
            'users',
            $userId,
            null,
            null,
            [
                'result' => 'created',
                'token_record_id' => $this->modelId($record),
                'ttl_minutes' => max(1, $ttlMinutes),
            ]
        );

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
        $record = $this->findValidPasswordResetToken($token);
        $wasUsed = $this->passwordResetTokenRepository->markUsed($this->hashToken($token));

        if ($wasUsed && $record !== null) {
            $userId = $this->modelUserId($record);
            $this->auditService->record(
                'password_reset_token_used',
                $userId,
                'users',
                $userId,
                null,
                null,
                [
                    'result' => 'used',
                    'token_record_id' => $this->modelId($record),
                ]
            );
        }

        return $wasUsed;
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
        $record = $this->findValidEmailVerificationToken($token);
        $wasUsed = $this->emailVerificationTokenRepository->markUsed($this->hashToken($token));

        if ($wasUsed && $record !== null) {
            $userId = $this->modelUserId($record);
            $this->auditService->record(
                'email_verification_token_used',
                $userId,
                'users',
                $userId,
                null,
                null,
                [
                    'result' => 'used',
                    'token_record_id' => $this->modelId($record),
                ]
            );
        }

        return $wasUsed;
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

    /**
     * Read a model id as nullable integer.
     */
    private function modelId(PasswordResetToken|EmailVerificationToken $model): ?int
    {
        $id = $model->toArray()['id'] ?? null;

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * Read a token user id as nullable integer.
     */
    private function modelUserId(PasswordResetToken|EmailVerificationToken $model): ?int
    {
        $userId = $model->toArray()['user_id'] ?? null;

        return is_numeric($userId) ? (int) $userId : null;
    }
}
