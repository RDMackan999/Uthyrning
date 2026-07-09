<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Exception used by the model and repository foundation.
 */
final class ModelException extends RuntimeException
{
    /**
     * Create a clear exception for methods prepared for later sprints.
     */
    public static function notImplemented(string $method): self
    {
        return new self(sprintf('%s is not implemented yet.', $method));
    }
}
