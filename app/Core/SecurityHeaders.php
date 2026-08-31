<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Provides conservative default HTTP security headers for all responses.
 */
final class SecurityHeaders
{
    /**
     * Merge configured security headers without overriding explicit response headers.
     *
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    public static function merge(array $headers): array
    {
        if (Config::get('security.headers.enabled', true) !== true) {
            return $headers;
        }

        foreach (self::defaults() as $name => $value) {
            if (self::hasHeader($headers, $name)) {
                continue;
            }

            $headers[$name] = $value;
        }

        return $headers;
    }

    /**
     * @return array<string, string>
     */
    private static function defaults(): array
    {
        return self::safeHeaders([
            'Content-Security-Policy' => Config::get(
                'security.headers.content_security_policy',
                "frame-ancestors 'none'; base-uri 'self'; form-action 'self'"
            ),
            'X-Frame-Options' => Config::get('security.headers.x_frame_options', 'DENY'),
            'X-Content-Type-Options' => Config::get('security.headers.x_content_type_options', 'nosniff'),
            'Referrer-Policy' => Config::get('security.headers.referrer_policy', 'strict-origin-when-cross-origin'),
            'Permissions-Policy' => Config::get(
                'security.headers.permissions_policy',
                'geolocation=(), microphone=(), camera=()'
            ),
        ]);
    }

    /**
     * @param array<string, mixed> $headers
     * @return array<string, string>
     */
    private static function safeHeaders(array $headers): array
    {
        $safeHeaders = [];

        foreach ($headers as $name => $value) {
            $value = trim((string) $value);

            if ($value === '' || str_contains($value, "\r") || str_contains($value, "\n")) {
                continue;
            }

            $safeHeaders[$name] = $value;
        }

        return $safeHeaders;
    }

    /**
     * @param array<string, string> $headers
     */
    private static function hasHeader(array $headers, string $name): bool
    {
        foreach (array_keys($headers) as $headerName) {
            if (strcasecmp((string) $headerName, $name) === 0) {
                return true;
            }
        }

        return false;
    }
}
