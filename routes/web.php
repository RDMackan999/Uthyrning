<?php

declare(strict_types=1);

use App\Controllers\HomeController;
use App\Core\Config;
use App\Core\Response;
use App\Core\Router;

return static function (Router $router): void {
    $router->get('/', static fn (): Response => (new HomeController())->index());

    $router->get('/health', static fn (): Response => Response::json([
        'status' => 'ok',
        'version' => (string) Config::get('app.version', '0.1.0'),
        'environment' => (string) Config::get('app.environment', 'development'),
    ]));
};
