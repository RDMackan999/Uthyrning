<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Exception for notification foundation errors that must not leak secrets.
 */
final class NotificationException extends RuntimeException
{
    public function __construct(string $message, private readonly string $safeErrorCode = 'delivery_exception')
    {
        parent::__construct($message);
    }

    public static function invalidRecipient(): self
    {
        return new self('Notification recipient is invalid.', 'recipient_rejected');
    }

    public static function unsupportedEvent(string $eventKey): self
    {
        return new self('Notification event is not supported: ' . $eventKey, 'delivery_failed');
    }

    public static function configurationError(): self
    {
        return new self('Email transport configuration is invalid.', 'configuration_error');
    }

    public static function productionTransportNotConfigured(): self
    {
        return new self('Production email transport must be configured explicitly.', 'configuration_error');
    }

    public function safeErrorCode(): string
    {
        return $this->safeErrorCode;
    }
}
