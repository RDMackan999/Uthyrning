<?php

declare(strict_types=1);

namespace App\Services\Email;

/**
 * Safe result object returned by email transports.
 */
final class EmailDeliveryResult
{
    private function __construct(
        private readonly bool $successful,
        private readonly ?string $messageId,
        private readonly ?string $errorCode,
        private readonly ?string $errorSummary,
    ) {
    }

    public static function success(?string $messageId = null): self
    {
        return new self(true, $messageId, null, null);
    }

    public static function failure(string $errorCode, string $errorSummary): self
    {
        return new self(false, null, $errorCode, $errorSummary);
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    public function messageId(): ?string
    {
        return $this->messageId;
    }

    public function errorCode(): ?string
    {
        return $this->errorCode;
    }

    public function errorSummary(): ?string
    {
        return $this->errorSummary;
    }
}
