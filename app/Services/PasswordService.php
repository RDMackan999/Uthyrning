<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Handles password policy checks and password hashing.
 */
final class PasswordService
{
    public const MIN_LENGTH = 12;
    public const MAX_LENGTH = 128;

    /**
     * Hash a password with PHP's current secure default algorithm.
     */
    public function hash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Verify a plain password against a stored password hash.
     */
    public function verify(string $password, string $passwordHash): bool
    {
        return password_verify($password, $passwordHash);
    }

    /**
     * Determine whether a stored hash should be upgraded.
     */
    public function needsRehash(string $passwordHash): bool
    {
        return password_needs_rehash($passwordHash, PASSWORD_DEFAULT);
    }

    /**
     * Validate the Version 1 password policy.
     *
     * @return array<int, string>
     */
    public function validatePolicy(string $password): array
    {
        $errors = [];
        $length = strlen($password);

        if ($length < self::MIN_LENGTH) {
            $errors[] = 'Password must be at least 12 characters.';
        }

        if ($length > self::MAX_LENGTH) {
            $errors[] = 'Password must not be longer than 128 characters.';
        }

        return $errors;
    }
}
