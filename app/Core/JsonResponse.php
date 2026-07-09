<?php

declare(strict_types=1);

namespace App\Core;

/**
 * HTTP response for JSON payloads.
 */
final class JsonResponse extends Response
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data, int $statusCode = 200)
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        parent::__construct(
            $json === false ? '{}' : $json,
            $statusCode,
            ['Content-Type' => 'application/json; charset=utf-8'],
        );
    }
}
