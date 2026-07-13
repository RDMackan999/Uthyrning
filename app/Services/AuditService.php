<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Repositories\AuditLogRepository;
use Throwable;

/**
 * Records sanitized append-only audit events.
 */
final class AuditService
{
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'password_hash',
        'token',
        'plain_token',
        'session_token',
        'session_cookie',
        'session_token_hash',
        'reset_token',
        'reset_token_hash',
        'verification_token',
        'verification_token_hash',
        'token_hash',
        'api_key',
        'authorization',
        'secret',
        'request_body',
        'body',
    ];

    public function __construct(
        private readonly AuditLogRepository $auditLogRepository = new AuditLogRepository()
    ) {
    }

    /**
     * Record an audit event with sanitized context.
     *
     * Audit write failures are intentionally not allowed to expose sensitive
     * details or break the caller flow in this foundation layer. A future
     * reliability sprint can add queueing or retry handling if needed.
     *
     * @param array<string, mixed> $context
     */
    public function record(
        string $eventName,
        ?int $actorUserId = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        array $context = []
    ): ?AuditLog {
        try {
            return $this->auditLogRepository->append(
                $this->normalizeEventName($eventName),
                $actorUserId,
                $this->truncate($subjectType, 100),
                $subjectId,
                $this->truncate($ipAddress, 45),
                $this->truncate($userAgent, 500),
                $this->sanitizeContext($context)
            );
        } catch (Throwable) {
            error_log('Audit write failed.');

            return null;
        }
    }

    /**
     * Remove sensitive values from audit context.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function sanitizeContext(array $context): array
    {
        $sanitized = [];

        foreach ($context as $key => $value) {
            if (!is_string($key) || $this->isSensitiveKey($key)) {
                continue;
            }

            $sanitized[$key] = $this->sanitizeValue($value);
        }

        return $sanitized;
    }

    /**
     * Normalize event names to a safe, searchable format.
     */
    private function normalizeEventName(string $eventName): string
    {
        $normalized = strtolower(trim($eventName));
        $normalized = preg_replace('/[^a-z0-9_.-]/', '_', $normalized) ?? 'unknown_event';

        return $this->truncate($normalized, 150) ?? 'unknown_event';
    }

    /**
     * Determine whether a context key must never be stored.
     */
    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(trim($key));

        if (in_array($normalized, self::SENSITIVE_KEYS, true)) {
            return true;
        }

        return str_contains($normalized, 'password')
            || str_contains($normalized, 'session_token')
            || str_contains($normalized, 'reset_token')
            || str_contains($normalized, 'verification_token')
            || str_contains($normalized, 'token_hash')
            || str_contains($normalized, 'cookie')
            || str_contains($normalized, 'authorization');
    }

    /**
     * Sanitize one context value.
     */
    private function sanitizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return $this->sanitizeContext($value);
        }

        if (is_string($value)) {
            return $this->truncate($value, 500);
        }

        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            return $value;
        }

        return '[unsupported]';
    }

    /**
     * Truncate optional text before it reaches the database.
     */
    private function truncate(?string $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        return substr($value, 0, $maxLength);
    }
}
