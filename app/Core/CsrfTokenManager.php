<?php

declare(strict_types=1);

namespace App\Core;

use Exception;

/**
 * File-backed CSRF tokens without PHP session_start().
 */
final class CsrfTokenManager
{
    private const ID_BYTES = 16;
    private const TOKEN_BYTES = 32;

    public function __construct(
        private readonly string $storageDirectory,
        private readonly string $cookieName,
        private readonly int $ttlSeconds,
        private readonly bool $isSecure,
    ) {
    }

    /**
     * Create a manager from application config.
     */
    public static function fromConfig(): self
    {
        $basePath = dirname(__DIR__, 2);

        return new self(
            $basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sessions' . DIRECTORY_SEPARATOR . 'csrf',
            (string) Config::get('auth.csrf_cookie_name', 'uthyrning_csrf'),
            (int) Config::get('auth.csrf_token_lifetime', 1800),
            (string) Config::get('app.environment', 'production') === 'production',
        );
    }

    /**
     * Generate and store a CSRF token hash server-side.
     *
     * @throws Exception
     */
    public function generateToken(Request $request): string
    {
        $csrfId = $this->csrfIdFromRequest($request) ?? bin2hex(random_bytes(self::ID_BYTES));
        $token = bin2hex(random_bytes(self::TOKEN_BYTES));
        $expiresAt = time() + max(300, $this->ttlSeconds);

        $this->ensureStorageDirectory();
        file_put_contents($this->pathForId($csrfId), json_encode([
            'token_hash' => hash('sha256', $token),
            'expires_at' => $expiresAt,
        ], JSON_THROW_ON_ERROR), LOCK_EX);

        $this->setCsrfCookie($csrfId, $expiresAt);

        return $token;
    }

    /**
     * Validate a submitted CSRF token against server-side storage.
     */
    public function validate(Request $request, ?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        $csrfId = $this->csrfIdFromRequest($request);
        if ($csrfId === null) {
            return false;
        }

        $path = $this->pathForId($csrfId);
        if (!is_file($path)) {
            return false;
        }

        $payload = json_decode((string) file_get_contents($path), true);
        if (!is_array($payload)) {
            return false;
        }

        $expiresAt = $payload['expires_at'] ?? null;
        $tokenHash = $payload['token_hash'] ?? null;

        if (!is_int($expiresAt) || $expiresAt < time() || !is_string($tokenHash)) {
            @unlink($path);

            return false;
        }

        $isValid = hash_equals($tokenHash, hash('sha256', $token));

        if ($isValid) {
            @unlink($path);
        }

        return $isValid;
    }

    /**
     * Read a safe CSRF id from request cookies.
     */
    private function csrfIdFromRequest(Request $request): ?string
    {
        $value = $request->cookie($this->cookieName);

        if (!is_string($value) || !preg_match('/^[a-f0-9]{32}$/', $value)) {
            return null;
        }

        return $value;
    }

    /**
     * Build the storage path for one CSRF id.
     */
    private function pathForId(string $csrfId): string
    {
        return rtrim($this->storageDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $csrfId . '.json';
    }

    /**
     * Ensure CSRF storage exists.
     */
    private function ensureStorageDirectory(): void
    {
        if (!is_dir($this->storageDirectory)) {
            mkdir($this->storageDirectory, 0775, true);
        }
    }

    /**
     * Store only the opaque CSRF id in a secure cookie.
     */
    private function setCsrfCookie(string $csrfId, int $expiresAt): void
    {
        setcookie($this->cookieName, $csrfId, [
            'expires' => $expiresAt,
            'path' => '/',
            'secure' => $this->isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
