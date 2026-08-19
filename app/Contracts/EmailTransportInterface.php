<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Services\Email\EmailDeliveryResult;
use App\Services\Email\EmailMessage;

/**
 * Contract for vendor-neutral email delivery.
 */
interface EmailTransportInterface
{
    public function send(EmailMessage $message): EmailDeliveryResult;
}
