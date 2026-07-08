<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Boots the PHP backend infrastructure and dispatches the router.
 */
final class Bootstrap
{
    public function __construct(private readonly string $basePath)
    {
    }

    /**
     * Create a bootstrap instance for the project root.
     */
    public static function create(?string $basePath = null): self
    {
        return new self($basePath ?? dirname(__DIR__, 2));
    }

    /**
     * Load config, register error handling, load routes and dispatch the request.
     */
    public function run(?Request $request = null): Response
    {
        Config::load($this->basePath);

        date_default_timezone_set((string) Config::get('app.timezone', 'Europe/Stockholm'));

        $logger = new Logger($this->basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs');
        $errorHandler = new ErrorHandler(
            (string) Config::get('app.environment', 'production'),
            (bool) Config::get('app.debug', false),
            $logger,
        );
        $errorHandler->register();

        $router = new Router();
        $this->loadRoutes($router);

        return $router->dispatch($request ?? Request::capture());
    }

    /**
     * Load route definitions from routes/web.php when the file exists.
     */
    private function loadRoutes(Router $router): void
    {
        $routesFile = $this->basePath . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php';

        if (!is_file($routesFile)) {
            return;
        }

        $registerRoutes = require $routesFile;

        if (is_callable($registerRoutes)) {
            $registerRoutes($router);
        }
    }
}
