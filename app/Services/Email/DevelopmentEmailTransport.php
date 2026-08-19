<?php

declare(strict_types=1);

namespace App\Services\Email;

use App\Contracts\EmailTransportInterface;

/**
 * Captures email messages in memory without sending real email.
 */
final class DevelopmentEmailTransport implements EmailTransportInterface
{
    /**
     * @var list<EmailMessage>
     */
    private array $capturedMessages = [];

    public function __construct(
        private bool $shouldFail = false,
        private readonly EmailMessageValidator $validator = new EmailMessageValidator()
    )
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
        $this->validator->validateMessage($message);
    }
}
