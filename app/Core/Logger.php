<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Writes simple application log entries to storage/logs without secrets.
 */
final class Logger
{
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'token',
        'api_key',
        'session_id',
        'personnummer',
        'personal_identity_number',
    ];

    public function __construct(private readonly string $logDirectory)
    {
    }

    /**
     * Write an INFO log entry.
     *
     * @param array<string, mixed> $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    /**
     * Write a WARNING log entry.
     *
     * @param array<string, mixed> $context
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }

    /**
     * Write an ERROR log entry.
     *
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    /**
     * Write a CRITICAL log entry.
     *
     * @param array<string, mixed> $context
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log('CRITICAL', $message, $context);
    }

    /**
     * Write a sanitized log entry to the daily log file.
     *
     * @param array<string, mixed> $context
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $level = strtoupper($level);
        $entry = sprintf(
            '[%s] %s: %s%s%s',
            date('Y-m-d H:i:s'),
            $level,
            $message,
            $context === [] ? '' : ' ',
            $context === [] ? '' : $this->encodeContext($this->sanitize($context)),
        );

        if (!is_dir($this->logDirectory) && !mkdir($this->logDirectory, 0775, true) && !is_dir($this->logDirectory)) {
            error_log($entry);
            return;
        }

        $path = $this->logDirectory . DIRECTORY_SEPARATOR . 'app-' . date('Y-m-d') . '.log';
        file_put_contents($path, $entry . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function encodeContext(array $context): string
    {
        $json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json === false ? '{}' : $json;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function sanitize(array $context): array
    {
        foreach ($context as $key => $value) {
            if (in_array(strtolower((string) $key), self::SENSITIVE_KEYS, true)) {
                $context[$key] = '[redacted]';
                continue;
            }

            if (is_array($value)) {
                $context[$key] = $this->sanitize($value);
            }
        }

        return $context;
    }
}
