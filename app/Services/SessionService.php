<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\UserSession;
use App\Repositories\UserSessionRepository;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Throwable;

/**
 * Manages server-side authentication session records without starting PHP sessions.
 */
final class SessionService
{
    public const SESSION_TTL_MINUTES = 480;
    public const INACTIVITY_LIMIT_MINUTES = 30;

    public function __construct(
        private readonly UserSessionRepository $userSessionRepository = new UserSessionRepository(),
        private readonly TokenService $tokenService = new TokenService(),
        private readonly AuditService $auditService = new AuditService()
    ) {
    }

    /**
     * Create a session record and return the clear token only to the caller.
     *
     * @return array{token: string, token_hash: string, expires_at: string, session: UserSession}
     *
     * @throws Exception
     */
    public function createSession(
        int $userId,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        int $ttlMinutes = self::SESSION_TTL_MINUTES
    ): array {
        $token = $this->tokenService->generateToken();
        $tokenHash = $this->tokenService->hashToken($token);
        $expiresAt = $this->expiresAt($ttlMinutes);
        $session = $this->userSessionRepository->createSession(
            $userId,
            $tokenHash,
            $this->truncate($ipAddress, 45),
            $this->truncate($userAgent, 500),
            $expiresAt
        );
        $this->auditService->record(
            'session_created',
            $userId,
            'user_sessions',
            $this->modelId($session),
            $ipAddress,
            $userAgent,
            [
                'result' => 'created',
                'session_id' => $this->modelId($session),
            ]
        );

        return [
            'token' => $token,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
            'session' => $session,
        ];
    }

    /**
     * Find a session by clear token.
     */
    public function findByToken(string $token): ?UserSession
    {
        return $this->userSessionRepository->findByTokenHash($this->tokenService->hashToken($token));
    }

    /**
     * Determine whether a session is still usable.
     */
    public function isValid(UserSession $session): bool
    {
        return !$this->isRevoked($session)
            && !$this->isExpired($session)
            && !$this->isInactive($session);
    }

    /**
     * Determine whether a session is revoked.
     */
    public function isRevoked(UserSession $session): bool
    {
        return $this->value($session, 'revoked_at') !== null;
    }

    /**
     * Determine whether a session has passed its absolute expiry.
     */
    public function isExpired(UserSession $session): bool
    {
        return $this->isPastTimestamp($this->value($session, 'expires_at'));
    }

    /**
     * Determine whether a session exceeded the inactivity limit.
     */
    public function isInactive(UserSession $session): bool
    {
        $lastActivityAt = $this->value($session, 'last_activity_at') ?? $this->value($session, 'created_at');

        if (!is_string($lastActivityAt) || $lastActivityAt === '') {
            return true;
        }

        try {
            $lastActivity = new DateTimeImmutable($lastActivityAt, new DateTimeZone('UTC'));
        } catch (Throwable) {
            return true;
        }

        $inactiveSince = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify(sprintf('-%d minutes', self::INACTIVITY_LIMIT_MINUTES));

        return $lastActivity <= $inactiveSince;
    }

    /**
     * Update activity timestamp for a still-valid session.
     */
    public function touch(string $token): bool
    {
        $session = $this->findByToken($token);

        if ($session === null || !$this->isValid($session)) {
            return false;
        }

        return $this->userSessionRepository->touch($this->tokenService->hashToken($token));
    }

    /**
     * Revoke one session by clear token.
     */
    public function revoke(string $token): bool
    {
        $session = $this->findByToken($token);
        $wasRevoked = $this->userSessionRepository->revoke($this->tokenService->hashToken($token));

        if ($wasRevoked && $session !== null) {
            $userId = $this->modelUserId($session);
            $this->auditService->record(
                'session_revoked',
                $userId,
                'user_sessions',
                $this->modelId($session),
                null,
                null,
                [
                    'result' => 'revoked',
                    'session_id' => $this->modelId($session),
                ]
            );
        }

        return $wasRevoked;
    }

    /**
     * Revoke all sessions for a user.
     */
    public function revokeAllForUser(int $userId): int
    {
        $revokedCount = $this->userSessionRepository->revokeAllForUser($userId);

        if ($revokedCount > 0) {
            $this->auditService->record(
                'all_sessions_revoked',
                $userId,
                'users',
                $userId,
                null,
                null,
                [
                    'result' => 'revoked',
                    'sessions_revoked' => $revokedCount,
                ]
            );
        }

        return $revokedCount;
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
     * Safely read a model attribute.
     */
    private function value(UserSession $session, string $key): mixed
    {
        return $session->toArray()[$key] ?? null;
    }

    /**
     * Read a model id as nullable integer.
     */
    private function modelId(UserSession $session): ?int
    {
        $id = $this->value($session, 'id');

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * Read a session user id as nullable integer.
     */
    private function modelUserId(UserSession $session): ?int
    {
        $userId = $this->value($session, 'user_id');

        return is_numeric($userId) ? (int) $userId : null;
    }

    /**
     * Determine whether a nullable timestamp is in the past.
     */
    private function isPastTimestamp(mixed $timestamp): bool
    {
        if (!is_string($timestamp) || $timestamp === '') {
            return true;
        }

        try {
            $date = new DateTimeImmutable($timestamp, new DateTimeZone('UTC'));
        } catch (Throwable) {
            return true;
        }

        return $date <= new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    /**
     * Truncate optional metadata before storage.
     */
    private function truncate(?string $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        return substr($value, 0, $maxLength);
    }
}
