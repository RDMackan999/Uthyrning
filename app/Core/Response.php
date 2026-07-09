<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Represents an HTTP response with status, headers and body content.
 */
class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private string $content = '',
        private int $statusCode = 200,
        private array $headers = [],
    ) {
    }

    /**
     * Create a plain text response.
     */
    public static function text(string $content, int $statusCode = 200): self
    {
        return new self($content, $statusCode, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    /**
     * Create an HTML response.
     */
    public static function html(string $content, int $statusCode = 200): self
    {
        return new self($content, $statusCode, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * Create a JSON response.
     *
     * @param array<string, mixed> $data
     */
    public static function json(array $data, int $statusCode = 200): self
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return new self(
            $json === false ? '{}' : $json,
            $statusCode,
            ['Content-Type' => 'application/json; charset=utf-8'],
        );
    }

    /**
     * Set the HTTP status code.
     */
    public function status(int $statusCode): self
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    /**
     * Set a response header.
     */
    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * Send the response to the client.
     */
    public function send(): void
    {
        http_response_code($this->statusCode);

        if (!headers_sent()) {
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }

        echo $this->content;
    }

    /**
     * Return response body content.
     */
    public function content(): string
    {
        return $this->content;
    }

    /**
     * Return HTTP status code.
     */
    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }
}
