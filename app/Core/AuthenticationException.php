<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Exception type for authentication service failures.
 */
final class AuthenticationException extends RuntimeException
{
    /**
     * Create a generic invalid credentials exception.
     */
    public static function invalidCredentials(): self
    {
        return new self('Invalid credentials or access is temporarily unavailable.');
    }

    /**
     * Create a generic throttled authentication exception.
     */
    public static function throttled(): self
    {
        return new self('Authentication is temporarily unavailable.');
    }
}
