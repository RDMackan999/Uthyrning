<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Base exception for expected HTTP errors.
 */
class HttpException extends RuntimeException
{
    public function __construct(private readonly int $statusCode, string $message = '')
    {
        parent::__construct($message);
    }

    /**
     * Return the HTTP status code for this exception.
     */
    public function statusCode(): int
    {
        return $this->statusCode;
    }
}
