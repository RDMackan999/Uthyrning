<?php

declare(strict_types=1);

use App\Controllers\AdminDashboardController;
use App\Controllers\AuthController;
use App\Controllers\HomeController;
use App\Controllers\RentalItemController;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Middleware\AuthenticationMiddleware;
use App\Middleware\AuthorizationMiddleware;

return static function (Router $router): void {
    $authController = new AuthController();
    $adminDashboardController = AdminDashboardController::fromConfig();
    $rentalItemController = RentalItemController::fromConfig();
    $authenticationMiddleware = AuthenticationMiddleware::fromConfig();
    $systemAdminMiddleware = [
        $authenticationMiddleware,
        new AuthorizationMiddleware(['system_admin']),
    ];

    $router->get('/', static fn (): Response => (new HomeController())->index());

    $router->get('/login', static fn (Request $request): Response => $authController->showLogin($request));
    $router->post('/login', static fn (Request $request): Response => $authController->login($request));
    $router->post(
        '/logout',
        static fn (Request $request): Response => $authController->logout($request),
        [$authenticationMiddleware]
    );
    $router->get(
        '/admin',
        static fn (Request $request): Response => $adminDashboardController->index($request),
        $systemAdminMiddleware
    );
    $router->get(
        '/admin/items',
        static fn (Request $request): Response => $rentalItemController->index($request),
        $systemAdminMiddleware
    );
    $router->get(
        '/admin/items/create',
        static fn (Request $request): Response => $rentalItemController->create($request),
        $systemAdminMiddleware
    );
    $router->post(
        '/admin/items',
        static fn (Request $request): Response => $rentalItemController->store($request),
        $systemAdminMiddleware
    );
    $router->get(
        '/admin/items/{public_id}/edit',
        static fn (Request $request): Response => $rentalItemController->edit($request),
        $systemAdminMiddleware
    );
    $router->post(
        '/admin/items/{public_id}',
        static fn (Request $request): Response => $rentalItemController->update($request),
        $systemAdminMiddleware
    );

    $router->get('/health', static fn (): Response => Response::json([
        'status' => 'ok',
        'version' => (string) Config::get('app.version', '0.1.0'),
        'environment' => (string) Config::get('app.environment', 'development'),
    ]));
};
