<?php

declare(strict_types=1);

namespace App\Services\Email;

/**
 * Transport-neutral email message value object.
 */
final class EmailMessage
{
    public function __construct(
        public readonly string $recipientEmail,
        public readonly string $subject,
        public readonly string $htmlBody,
        public readonly string $textBody,
    ) {
    }
}
