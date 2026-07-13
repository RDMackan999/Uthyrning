<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Contract for small HTTP middleware classes.
 */
interface MiddlewareInterface
{
    /**
     * Handle a request before passing it to the next middleware or route handler.
     *
     * @param callable(Request): Response $next
     */
    public function handle(Request $request, callable $next): Response;
}
