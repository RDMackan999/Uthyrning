<?php

declare(strict_types=1);

namespace App\Core;

use Closure;

/**
 * Small HTTP router for exact GET and POST route matching.
 */
final class Router
{
    /**
     * @var array<string, array<string, callable(Request): mixed>>
     */
    private array $routes = [];

    /**
     * Register a route for a HTTP method and path.
     */
    public function add(string $method, string $path, callable $handler): void
    {
        $method = strtoupper($method);
        $path = $this->normalizePath($path);

        $this->routes[$method][$path] = $handler(...);
    }

    /**
     * Register a GET route.
     */
    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    /**
     * Register a POST route.
     */
    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    /**
     * Dispatch a request to a matching route or return 404.
     */
    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $path = $this->normalizePath($request->path());
        $handler = $this->routes[$method][$path] ?? null;

        if (!$handler instanceof Closure) {
            return Response::text('Not Found', 404);
        }

        return $this->toResponse($handler($request));
    }

    /**
     * Convert supported route return values to Response objects.
     */
    private function toResponse(mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }

        if (is_array($result)) {
            return Response::json($result);
        }

        return Response::text((string) $result);
    }

    /**
     * Normalize paths for exact matching.
     */
    private function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
