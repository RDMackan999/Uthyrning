<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Exception for notification foundation errors that must not leak secrets.
 */
final class NotificationException extends RuntimeException
{
    public static function invalidRecipient(): self
    {
        return new self('Notification recipient is invalid.');
    }

    public static function unsupportedEvent(string $eventKey): self
    {
        return new self('Notification event is not supported: ' . $eventKey);
    }
}
