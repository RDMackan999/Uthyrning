<?php

declare(strict_types=1);

namespace App\Core;

use ErrorException;
use Throwable;

/**
 * Registers simple PHP error and exception handling for the backend.
 */
final class ErrorHandler
{
    public function __construct(
        private readonly string $environment,
        private readonly bool $debug,
        private readonly Logger $logger,
    ) {
    }

    /**
     * Register PHP error and exception handlers.
     */
    public function register(): void
    {
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
    }

    /**
     * Convert PHP errors to exceptions so they are handled consistently.
     */
    public function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if ((error_reporting() & $severity) === 0) {
            return false;
        }

        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    /**
     * Log exceptions and render a safe response.
     */
    public function handleException(Throwable $exception): void
    {
        $this->logger->error($exception->getMessage(), [
            'exception' => $exception::class,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);

        $response = $this->isDevelopment()
            ? Response::text($this->developmentMessage($exception), 500)
            : Response::text('Ett tekniskt fel uppstod.', 500);

        $response->send();
    }

    /**
     * Determine whether detailed errors may be shown.
     */
    private function isDevelopment(): bool
    {
        return $this->debug && in_array(strtolower($this->environment), ['development', 'local', 'dev'], true);
    }

    /**
     * Build a concise development error message.
     */
    private function developmentMessage(Throwable $exception): string
    {
        return sprintf(
            "%s\n%s\n%s:%d",
            $exception::class,
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
        );
    }
}
