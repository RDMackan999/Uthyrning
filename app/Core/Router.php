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
     * @var array<string, array<string, array{handler: Closure, middleware: list<MiddlewareInterface>}>>
     */
    private array $routes = [];

    /**
     * Register a route for a HTTP method and path.
     *
     * @param list<MiddlewareInterface> $middleware
     */
    public function add(string $method, string $path, callable $handler, array $middleware = []): void
    {
        $method = strtoupper($method);
        $path = $this->normalizePath($path);

        $this->routes[$method][$path] = [
            'handler' => $handler(...),
            'middleware' => $middleware,
        ];
    }

    /**
     * Register a GET route.
     *
     * @param list<MiddlewareInterface> $middleware
     */
    public function get(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    /**
     * Register a POST route.
     *
     * @param list<MiddlewareInterface> $middleware
     */
    public function post(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    /**
     * Dispatch a request to a matching route.
     */
    public function dispatch(Request $request): Response
    {
        try {
            $method = $request->method();
            $path = $this->normalizePath($request->path());
            $route = $this->routes[$method][$path] ?? null;

            if (!is_array($route)) {
                throw new NotFoundException();
            }

            return $this->runRoute($route['handler'], $route['middleware'], $request);
        } catch (HttpException $exception) {
            return Response::text($exception->getMessage(), $exception->statusCode());
        }
    }

    /**
     * Run route middleware in registration order before the route handler.
     *
     * @param list<MiddlewareInterface> $middleware
     */
    private function runRoute(Closure $handler, array $middleware, Request $request): Response
    {
        $next = fn (Request $request): Response => $this->toResponse($handler($request));

        foreach (array_reverse($middleware) as $middlewareItem) {
            $next = fn (Request $request): Response => $middlewareItem->handle($request, $next);
        }

        return $next($request);
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
            return new JsonResponse($result);
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
