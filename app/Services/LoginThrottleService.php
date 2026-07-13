<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LoginAttempt;
use App\Repositories\LoginAttemptRepository;

/**
 * Coordinates login attempt tracking and temporary throttling decisions.
 */
final class LoginThrottleService
{
    public const IDENTIFIER_LIMIT = 5;
    public const IDENTIFIER_WINDOW_MINUTES = 15;
    public const IDENTIFIER_LOCK_MINUTES = 15;
    public const IP_LIMIT = 20;
    public const IP_WINDOW_MINUTES = 15;
    public const IP_LOCK_MINUTES = 30;

    public function __construct(
        private readonly LoginAttemptRepository $loginAttemptRepository = new LoginAttemptRepository()
    ) {
    }

    /**
     * Return the current throttling state for one identifier and IP address.
     *
     * @return array<string, bool|int|string|null>
     */
    public function check(string $identifier, string $ipAddress): array
    {
        $normalizedIdentifier = $this->normalizeIdentifier($identifier);
        $identifierFailures = $this->loginAttemptRepository->countRecentFailuresByIdentifier(
            $normalizedIdentifier,
            self::IDENTIFIER_WINDOW_MINUTES
        );
        $ipFailures = $this->loginAttemptRepository->countRecentFailuresByIp(
            $ipAddress,
            self::IP_WINDOW_MINUTES
        );

        if ($identifierFailures >= self::IDENTIFIER_LIMIT) {
            return $this->blockedState($identifierFailures, $ipFailures, 'identifier', self::IDENTIFIER_LOCK_MINUTES);
        }

        if ($ipFailures >= self::IP_LIMIT) {
            return $this->blockedState($identifierFailures, $ipFailures, 'ip', self::IP_LOCK_MINUTES);
        }

        return [
            'is_blocked' => false,
            'reason' => null,
            'lock_minutes' => 0,
            'identifier_failures' => $identifierFailures,
            'ip_failures' => $ipFailures,
        ];
    }

    /**
     * Record one authentication attempt.
     */
    public function recordAttempt(
        string $identifier,
        ?int $userId,
        string $ipAddress,
        bool $wasSuccessful
    ): LoginAttempt {
        return $this->loginAttemptRepository->recordAttempt(
            $this->normalizeIdentifier($identifier),
            $userId,
            $ipAddress,
            $wasSuccessful
        );
    }

    /**
     * Normalize identifiers consistently before rate-limit checks.
     */
    public function normalizeIdentifier(string $identifier): string
    {
        return strtolower(trim($identifier));
    }

    /**
     * Build a consistent blocked response.
     *
     * @return array<string, bool|int|string|null>
     */
    private function blockedState(
        int $identifierFailures,
        int $ipFailures,
        string $reason,
        int $lockMinutes
    ): array {
        return [
            'is_blocked' => true,
            'reason' => $reason,
            'lock_minutes' => $lockMinutes,
            'identifier_failures' => $identifierFailures,
            'ip_failures' => $ipFailures,
        ];
    }
}
