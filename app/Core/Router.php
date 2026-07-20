<?php

declare(strict_types=1);

namespace App\Core;

use Closure;

/**
 * Small HTTP router for GET and POST route matching.
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
            $route = $this->matchRoute($method, $path);

            if (!is_array($route)) {
                throw new NotFoundException();
            }

            $request->setRouteParams($route['params']);

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
     * Match exact routes first and then simple {parameter} route patterns.
     *
     * @return array{handler: Closure, middleware: list<MiddlewareInterface>, params: array<string, string>}|null
     */
    private function matchRoute(string $method, string $path): ?array
    {
        $methodRoutes = $this->routes[$method] ?? [];
        $exactRoute = $methodRoutes[$path] ?? null;

        if (is_array($exactRoute)) {
            return [
                'handler' => $exactRoute['handler'],
                'middleware' => $exactRoute['middleware'],
                'params' => [],
            ];
        }

        foreach ($methodRoutes as $routePath => $route) {
            if (!str_contains($routePath, '{')) {
                continue;
            }

            $params = $this->matchParameterizedRoute($routePath, $path);

            if ($params === null) {
                continue;
            }

            return [
                'handler' => $route['handler'],
                'middleware' => $route['middleware'],
                'params' => $params,
            ];
        }

        return null;
    }

    /**
     * Match route patterns such as /admin/items/{public_id}/edit.
     *
     * @return array<string, string>|null
     */
    private function matchParameterizedRoute(string $routePath, string $requestPath): ?array
    {
        $routeSegments = explode('/', trim($routePath, '/'));
        $requestSegments = explode('/', trim($requestPath, '/'));

        if (count($routeSegments) !== count($requestSegments)) {
            return null;
        }

        $params = [];

        foreach ($routeSegments as $index => $routeSegment) {
            $requestSegment = $requestSegments[$index] ?? '';

            if (preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)\}$/', $routeSegment, $matches) === 1) {
                $params[$matches[1]] = rawurldecode($requestSegment);

                continue;
            }

            if ($routeSegment !== $requestSegment) {
                return null;
            }
        }

        return $params;
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
