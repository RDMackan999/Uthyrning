<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Represents the current HTTP request in a small framework-free shape.
 */
final class Request
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     */
    public function __construct(
        private readonly string $method,
        private readonly string $uri,
        private readonly array $query = [],
        private readonly array $post = [],
    ) {
    }

    /**
     * Build a request from PHP superglobals.
     */
    public static function capture(): self
    {
        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            (string) ($_SERVER['REQUEST_URI'] ?? '/'),
            $_GET,
            $_POST,
        );
    }

    /**
     * Return the normalized HTTP method.
     */
    public function method(): string
    {
        return strtoupper($this->method);
    }

    /**
     * Return the original request URI.
     */
    public function uri(): string
    {
        return $this->uri;
    }

    /**
     * Return the URI path without query string.
     */
    public function path(): string
    {
        $path = parse_url($this->uri, PHP_URL_PATH);

        if (!is_string($path) || $path === '') {
            return '/';
        }

        return '/' . trim($path, '/');
    }

    /**
     * Read query string data, or return all query data when key is omitted.
     */
    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }

        return $this->query[$key] ?? $default;
    }

    /**
     * Read POST data, or return all POST data when key is omitted.
     */
    public function post(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->post;
        }

        return $this->post[$key] ?? $default;
    }
}
