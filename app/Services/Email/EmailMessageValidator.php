<?php

declare(strict_types=1);

namespace App\Services\Email;

use App\Core\NotificationException;

/**
 * Validates email message and header values before transport delivery.
 */
final class EmailMessageValidator
{
    /**
     * Validate the common outbound message contract.
     */
    public function validateMessage(EmailMessage $message): void
    {
        $this->validateAddress($message->recipientEmail, 'recipient', 'recipient_rejected');
        $this->validateHeader($message->subject, 'subject');

        if (trim($message->htmlBody) === '' || trim($message->textBody) === '') {
            throw new NotificationException('Email body is invalid.', 'delivery_failed');
        }
    }

    /**
     * Validate an email address header.
     */
    public function validateAddress(string $email, string $fieldName = 'email', string $safeErrorCode = 'configuration_error'): void
    {
        $email = trim($email);

        if (!$this->isHeaderSafe($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new NotificationException('Email ' . $fieldName . ' is invalid.', $safeErrorCode);
        }
    }

    /**
     * Validate an arbitrary mail header value.
     */
    public function validateHeader(string $value, string $fieldName): void
    {
        if (!$this->isHeaderSafe($value) || trim($value) === '') {
            throw new NotificationException('Email ' . $fieldName . ' is invalid.', 'configuration_error');
        }
    }

    /**
     * Validate an optional mail header value.
     */
    public function validateOptionalHeader(?string $value, string $fieldName): void
    {
        if ($value === null || trim($value) === '') {
            return;
        }

        $this->validateHeader($value, $fieldName);
    }

    private function isHeaderSafe(string $value): bool
    {
        return !str_contains($value, "\r") && !str_contains($value, "\n");
    }
}
