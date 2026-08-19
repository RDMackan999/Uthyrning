<?php

declare(strict_types=1);

use App\Controllers\AdminDashboardController;
use App\Controllers\AdminAvailabilityBlockController;
use App\Controllers\AdminBookingController;
use App\Controllers\AdminNotificationController;
use App\Controllers\AuthController;
use App\Controllers\HomeController;
use App\Controllers\ItemRateController;
use App\Controllers\PublicBookingController;
use App\Controllers\PublicRentalItemController;
use App\Controllers\RentalItemController;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Middleware\AuthenticationMiddleware;
use App\Middleware\AuthorizationMiddleware;

return static function (Router $router): void {
    $authController = new AuthController();
    $adminAvailabilityBlockController = AdminAvailabilityBlockController::fromConfig();
    $adminBookingController = AdminBookingController::fromConfig();
    $adminDashboardController = AdminDashboardController::fromConfig();
    $adminNotificationController = AdminNotificationController::fromConfig();
    $publicBookingController = new PublicBookingController();
    $publicRentalItemController = new PublicRentalItemController();
    $rentalItemController = RentalItemController::fromConfig();
    $itemRateController = ItemRateController::fromConfig();
    $authenticationMiddleware = AuthenticationMiddleware::fromConfig();
    $systemAdminMiddleware = [
        $authenticationMiddleware,
        new AuthorizationMiddleware(['system_admin']),
    ];

    $router->get('/', static fn (): Response => (new HomeController())->index());
    $router->get('/items', static fn (Request $request): Response => $publicRentalItemController->index($request));
    $router->get(
        '/items/{public_id}/{slug}',
        static fn (Request $request): Response => $publicRentalItemController->show($request)
    );
    $router->get(
        '/items/{public_id}/{slug}/book',
        static fn (Request $request): Response => $publicBookingController->create($request)
    );
    $router->post(
        '/items/{public_id}/{slug}/book',
        static fn (Request $request): Response => $publicBookingController->store($request)
    );
    $router->get(
        '/bookings/{public_id}/confirmation',
        static fn (Request $request): Response => $publicBookingController->confirmation($request)
    );

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
        '/admin/bookings',
        static fn (Request $request): Response => $adminBookingController->index($request),
        $systemAdminMiddleware
    );
    $router->get(
        '/admin/bookings/{public_id}',
        static fn (Request $request): Response => $adminBookingController->show($request),
        $systemAdminMiddleware
    );
    $router->post(
        '/admin/bookings/{public_id}/approve',
        static fn (Request $request): Response => $adminBookingController->approve($request),
        $systemAdminMiddleware
    );
    $router->post(
        '/admin/bookings/{public_id}/reject',
        static fn (Request $request): Response => $adminBookingController->reject($request),
        $systemAdminMiddleware
    );
    $router->post(
        '/admin/bookings/{public_id}/cancel',
        static fn (Request $request): Response => $adminBookingController->cancel($request),
        $systemAdminMiddleware
    );
    $router->post(
        '/admin/bookings/{public_id}/start',
        static fn (Request $request): Response => $adminBookingController->start($request),
        $systemAdminMiddleware
    );
    $router->post(
        '/admin/bookings/{public_id}/complete',
        static fn (Request $request): Response => $adminBookingController->complete($request),
        $systemAdminMiddleware
    );
    $router->get(
        '/admin/notifications',
        static fn (Request $request): Response => $adminNotificationController->index($request),
        $systemAdminMiddleware
    );
    $router->get(
        '/admin/notifications/{public_id}',
        static fn (Request $request): Response => $adminNotificationController->show($request),
        $systemAdminMiddleware
    );
    $router->post(
        '/admin/notifications/{public_id}/retry',
        static fn (Request $request): Response => $adminNotificationController->retry($request),
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
    $router->get(
        '/admin/items/{public_id}/availability',
        static fn (Request $request): Response => $adminAvailabilityBlockController->index($request),
        $systemAdminMiddleware
    );
    $router->get(
        '/admin/items/{public_id}/availability/create',
        static fn (Request $request): Response => $adminAvailabilityBlockController->create($request),
        $systemAdminMiddleware
    );
    $router->post(
        '/admin/items/{public_id}/availability',
        static fn (Request $request): Response => $adminAvailabilityBlockController->store($request),
        $systemAdminMiddleware
    );
    $router->post(
        '/admin/items/{public_id}/availability/{id}/archive',
        static fn (Request $request): Response => $adminAvailabilityBlockController->archive($request),
        $systemAdminMiddleware
    );
    $router->get(
        '/admin/items/{public_id}/rates',
        static fn (Request $request): Response => $itemRateController->index($request),
        $systemAdminMiddleware
    );
    $router->get(
        '/admin/items/{public_id}/rates/create',
        static fn (Request $request): Response => $itemRateController->create($request),
        $systemAdminMiddleware
    );
    $router->post(
        '/admin/items/{public_id}/rates',
        static fn (Request $request): Response => $itemRateController->store($request),
        $systemAdminMiddleware
    );
    $router->get(
        '/admin/items/{public_id}/rates/{id}/edit',
        static fn (Request $request): Response => $itemRateController->edit($request),
        $systemAdminMiddleware
    );
    $router->post(
        '/admin/items/{public_id}/rates/{id}',
        static fn (Request $request): Response => $itemRateController->update($request),
        $systemAdminMiddleware
    );
    $router->post(
        '/admin/items/{public_id}/rates/{id}/archive',
        static fn (Request $request): Response => $itemRateController->archive($request),
        $systemAdminMiddleware
    );

    $router->get('/health', static fn (): Response => Response::json([
        'status' => 'ok',
        'version' => (string) Config::get('app.version', '0.1.0'),
        'environment' => (string) Config::get('app.environment', 'development'),
    ]));
};
