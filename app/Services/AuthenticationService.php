<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use Exception;

/**
 * Coordinates local email/password authentication without HTTP or UI concerns.
 */
final class AuthenticationService
{
    private const ACTIVE_STATUS = 'active';
    private const GENERIC_FAILURE_MESSAGE = 'Invalid credentials or access is temporarily unavailable.';

    public function __construct(
        private readonly UserRepository $userRepository = new UserRepository(),
        private readonly PasswordService $passwordService = new PasswordService(),
        private readonly LoginThrottleService $loginThrottleService = new LoginThrottleService(),
        private readonly SessionService $sessionService = new SessionService()
    ) {
    }

    /**
     * Attempt local email/password login and create a server-side session on success.
     *
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function attemptLogin(
        string $email,
        string $password,
        string $ipAddress,
        ?string $userAgent = null
    ): array {
        $normalizedEmail = $this->normalizeEmail($email);
        $throttleState = $this->loginThrottleService->check($normalizedEmail, $ipAddress);

        if ($throttleState['is_blocked'] === true) {
            $this->loginThrottleService->recordAttempt($normalizedEmail, null, $ipAddress, false);

            return $this->failureResponse(true, $throttleState);
        }

        $user = $this->userRepository->findByEmail($normalizedEmail);

        if ($user === null) {
            $this->loginThrottleService->recordAttempt($normalizedEmail, null, $ipAddress, false);

            return $this->failureResponse(false, $throttleState);
        }

        $userId = $this->userId($user);
        $passwordHash = $this->passwordHash($user);

        if (!$this->canAuthenticate($user) || $passwordHash === null || !$this->passwordService->verify($password, $passwordHash)) {
            $this->loginThrottleService->recordAttempt($normalizedEmail, $userId, $ipAddress, false);

            return $this->failureResponse(false, $throttleState);
        }

        $this->loginThrottleService->recordAttempt($normalizedEmail, $userId, $ipAddress, true);
        $session = $this->sessionService->createSession($userId, $ipAddress, $userAgent);

        return [
            'success' => true,
            'message' => 'Authentication successful.',
            'user' => $this->safeUserData($user),
            'session_token' => $session['token'],
            'session' => $session['session']->toArray(),
            'expires_at' => $session['expires_at'],
            'password_needs_rehash' => $this->passwordService->needsRehash($passwordHash),
        ];
    }

    /**
     * Normalize email addresses consistently before lookup.
     */
    public function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    /**
     * Return a generic failure response that does not reveal account existence.
     *
     * @param array<string, mixed> $throttleState
     *
     * @return array<string, mixed>
     */
    private function failureResponse(bool $isBlocked, array $throttleState): array
    {
        return [
            'success' => false,
            'message' => self::GENERIC_FAILURE_MESSAGE,
            'user' => null,
            'session_token' => null,
            'session' => null,
            'is_blocked' => $isBlocked,
            'throttle' => $throttleState,
        ];
    }

    /**
     * Ensure the account is active before allowing authentication.
     */
    private function canAuthenticate(User $user): bool
    {
        $data = $user->toArray();

        return ($data['status_key'] ?? null) === self::ACTIVE_STATUS
            && ($data['deleted_at'] ?? null) === null;
    }

    /**
     * Extract the technical user id.
     */
    private function userId(User $user): int
    {
        return (int) ($user->toArray()['id'] ?? 0);
    }

    /**
     * Extract stored password hash without exposing it.
     */
    private function passwordHash(User $user): ?string
    {
        $passwordHash = $user->toArray()['password_hash'] ?? null;

        return is_string($passwordHash) && $passwordHash !== '' ? $passwordHash : null;
    }

    /**
     * Return user data without password material.
     *
     * @return array<string, mixed>
     */
    private function safeUserData(User $user): array
    {
        $data = $user->toArray();
        unset($data['password_hash']);

        return $data;
    }
}
