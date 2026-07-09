<?php

declare(strict_types=1);

namespace App\Core;

/**
 * HTTP redirect response with an empty body and Location header.
 */
final class RedirectResponse extends Response
{
    public function __construct(string $url, int $status = 302)
    {
        parent::__construct('', $status, ['Location' => $url]);
    }
}
