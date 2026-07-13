<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Manages authentication cookies without logging cookie values.
 */
final class CookieManager
{
    public function __construct(
        private readonly string $sessionCookieName,
        private readonly int $sessionLifetimeSeconds,
        private readonly bool $isSecure,
    ) {
    }

    /**
     * Create a CookieManager from application config.
     */
    public static function fromConfig(): self
    {
        return new self(
            (string) Config::get('auth.session_cookie_name', 'uthyrning_session'),
            (int) Config::get('auth.session_cookie_lifetime', 28800),
            (string) Config::get('app.environment', 'production') === 'production',
        );
    }

    /**
     * Set the server-side session token cookie.
     */
    public function setSessionCookie(string $sessionToken): void
    {
        $this->setCookie($sessionToken, time() + $this->sessionLifetimeSeconds);
    }

    /**
     * Delete the session cookie.
     */
    public function deleteSessionCookie(): void
    {
        $this->setCookie('', time() - 3600);
    }

    /**
     * Read the current session token from the request cookie.
     */
    public function sessionToken(Request $request): ?string
    {
        $value = $request->cookie($this->sessionCookieName);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Return configured session cookie name.
     */
    public function sessionCookieName(): string
    {
        return $this->sessionCookieName;
    }

    /**
     * Write the cookie with secure defaults.
     */
    private function setCookie(string $value, int $expires): void
    {
        setcookie($this->sessionCookieName, $value, [
            'expires' => $expires,
            'path' => '/',
            'secure' => $this->isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
