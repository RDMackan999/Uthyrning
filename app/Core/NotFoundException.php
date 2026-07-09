<?php

declare(strict_types=1);

namespace App\Core;

/**
 * HTTP 404 exception for missing routes or resources.
 */
final class NotFoundException extends HttpException
{
    public function __construct(string $message = 'Not Found')
    {
        parent::__construct(404, $message);
    }
}
