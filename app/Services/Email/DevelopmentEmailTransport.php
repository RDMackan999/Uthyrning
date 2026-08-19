<?php

declare(strict_types=1);

namespace App\Services\Email;

use App\Contracts\EmailTransportInterface;
use App\Core\NotificationException;

/**
 * Captures email messages in memory without sending real email.
 */
final class DevelopmentEmailTransport implements EmailTransportInterface
{
    /**
     * @var list<EmailMessage>
     */
    private array $capturedMessages = [];

    public function __construct(private bool $shouldFail = false)
    {
    }

    public function send(EmailMessage $message): EmailDeliveryResult
    {
        $this->validateMessage($message);

        if ($this->shouldFail) {
            return EmailDeliveryResult::failure('development_failure', 'Development transport simulated failure.');
        }

        $this->capturedMessages[] = $message;

        return EmailDeliveryResult::success('development-capture-' . count($this->capturedMessages));
    }

    /**
     * @return list<EmailMessage>
     */
    public function capturedMessages(): array
    {
        return $this->capturedMessages;
    }

    public function simulateFailure(bool $shouldFail): void
    {
        $this->shouldFail = $shouldFail;
    }

    private function validateMessage(EmailMessage $message): void
    {
        if (!$this->isValidHeaderValue($message->recipientEmail) || !filter_var($message->recipientEmail, FILTER_VALIDATE_EMAIL)) {
            throw NotificationException::invalidRecipient();
        }

        if (!$this->isValidHeaderValue($message->subject) || trim($message->subject) === '') {
            throw new NotificationException('Email subject is invalid.');
        }

        if (trim($message->htmlBody) === '' || trim($message->textBody) === '') {
            throw new NotificationException('Email body is invalid.');
        }
    }

    private function isValidHeaderValue(string $value): bool
    {
        return !str_contains($value, "\r") && !str_contains($value, "\n");
    }
}
