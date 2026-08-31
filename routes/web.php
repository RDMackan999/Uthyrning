<?php

declare(strict_types=1);

use App\Controllers\AdminDashboardController;
use App\Controllers\AdminAvailabilityBlockController;
use App\Controllers\AdminBookingController;
use App\Controllers\AdminCustomerController;
use App\Controllers\AdminNotificationController;
use App\Controllers\AdminRentalFulfillmentController;
use App\Controllers\AuthController;
use App\Controllers\HomeController;
use App\Controllers\ItemRateController;
use App\Controllers\MediaDeliveryController;
use App\Controllers\OrganizationAdminAssignmentController;
use App\Controllers\PublicBookingController;
use App\Controllers\PublicRentalItemController;
use App\Controllers\RentalItemMediaController;
use App\Controllers\RentalItemController;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Middleware\AuthenticationMiddleware;
use App\Middleware\AuthorizationMiddleware;

return static function (Router $router): void {
    $authController = new AuthController();
    $adminAvailabilityBlockController = AdminAvailabilityBlockController::fromConfig();
    $adminBookingController = AdminBookingController::fromConfig();
    $adminCustomerController = AdminCustomerController::fromConfig();
    $adminDashboardController = AdminDashboardController::fromConfig();
    $adminNotificationController = AdminNotificationController::fromConfig();
    $adminRentalFulfillmentController = AdminRentalFulfillmentController::fromConfig();
    $mediaDeliveryController = new MediaDeliveryController();
    $organizationAdminAssignmentController = OrganizationAdminAssignmentController::fromConfig();
    $publicBookingController = new PublicBookingController();
    $publicRentalItemController = new PublicRentalItemController();
    $rentalItemMediaController = RentalItemMediaController::fromConfig();
    $rentalItemController = RentalItemController::fromConfig();
    $itemRateController = ItemRateController::fromConfig();
    $authenticationMiddleware = AuthenticationMiddleware::fromConfig();
    $adminMiddleware = [
        $authenticationMiddleware,
        new AuthorizationMiddleware(['system_admin', 'organization_admin']),
    ];
    $systemAdminMiddleware = [
        $authenticationMiddleware,
        new AuthorizationMiddleware(['system_admin']),
    ];

    $router->get('/', static fn (): Response => (new HomeController())->index());
    $router->get('/items', static fn (Request $request): Response => $publicRentalItemController->index($request));
    $router->get(
        '/media/{public_id}/{variant}',
        static fn (Request $request): Response => $mediaDeliveryController->publicImage($request)
    );
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
        $adminMiddleware
    );
    $router->get(
        '/admin/bookings',
        static fn (Request $request): Response => $adminBookingController->index($request),
        $adminMiddleware
    );
    $router->get(
        '/admin/bookings/{public_id}',
        static fn (Request $request): Response => $adminBookingController->show($request),
        $adminMiddleware
    );
    $router->post(
        '/admin/bookings/{public_id}/approve',
        static fn (Request $request): Response => $adminBookingController->approve($request),
        $adminMiddleware
    );
    $router->post(
        '/admin/bookings/{public_id}/reject',
        static fn (Request $request): Response => $adminBookingController->reject($request),
        $adminMiddleware
    );
    $router->post(
        '/admin/bookings/{public_id}/cancel',
        static fn (Request $request): Response => $adminBookingController->cancel($request),
        $adminMiddleware
    );
    $router->get(
        '/admin/bookings/{public_id}/handover',
        static fn (Request $request): Response => $adminRentalFulfillmentController->handover($request),
        $adminMiddleware
    );
    $router->post(
        '/admin/bookings/{public_id}/handover',
        static fn (Request $request): Response => $adminRentalFulfillmentController->storeHandover($request),
        $adminMiddleware
    );
    $router->get(
        '/admin/bookings/{public_id}/return',
        static fn (Request $request): Response => $adminRentalFulfillmentController->returnForm($request),
        $adminMiddleware
    );
    $router->post(
        '/admin/bookings/{public_id}/return',
        static fn (Request $request): Response => $adminRentalFulfillmentController->storeReturn($request),
        $adminMiddleware
    );
    $router->post(
        '/admin/bookings/{public_id}/start',
        static fn (Request $request): Response => $adminBookingController->start($request),
        $adminMiddleware
    );
    $router->post(
        '/admin/bookings/{public_id}/complete',
        static fn (Request $request): Response => $adminBookingController->complete($request),
        $adminMiddleware
    );
    $router->get(
        '/admin/notifications',
        static fn (Request $request): Response => $adminNotificationController->index($request),
        $adminMiddleware
    );
    $router->get(
        '/admin/notifications/{public_id}',
        static fn (Request $request): Response => $adminNotificationController->show($request),
        $adminMiddleware
    );
    $router->post(
        '/admin/notifications/{public_id}/retry',
        static fn (Request $request): Response => $adminNotificationController->retry($request),
        $adminMiddleware
    );
    $router->get(
        '/admin/organization-admins',
        static fn (Request $request): Response => $organizationAdminAssignmentController->index($request),
        $systemAdminMiddleware
    );
    $router->get(
        '/admin/organization-admins/assign',
        static fn (Request $request): Response => $organizationAdminAssignmentController->assign($request),
        $systemAdminMiddleware
    );
    $router->post(
        '/admin/organization-admins',
        static fn (Request $request): Response => $organizationAdminAssignmentController->store($request),
        $systemAdminMiddleware
    );
    $router->post(
        '/admin/organization-admins/{user_id}/{organization_id}/revoke',
        static fn (Request $request): Response => $organizationAdminAssignmentController->revoke($request),
        $systemAdminMiddleware
    );
    $router->get(
        '/admin/customers',
        static fn (Request $request): Response => $adminCustomerController->index($request),
        $adminMiddleware
    );
    $router->get(
        '/admin/customers/{id}',
        static fn (Request $request): Response => $adminCustomerController->show($request),
        $adminMiddleware
    );
    $router->get(
        '/admin/customers/{id}/edit',
        static fn (Request $request): Response => $adminCustomerController->edit($request),
        $adminMiddleware
    );
    $router->post(
        '/admin/customers/{id}',
        static fn (Request $request): Response => $adminCustomerController->update($request),
        $adminMiddleware
    );
    $router->post(
        '/admin/customers/{id}/status',
        static fn (Request $request): Response => $adminCustomerController->updateStatus($request),
        $adminMiddleware
    );
    $router->get(
        '/admin/items',
        static fn (Request $request): Response => $rentalItemController->index($request),
        $adminMiddleware
    );
    $router->get(
        '/admin/items/create',
        static fn (Request $request): Response => $rentalItemController->create($request),
        $adminMiddleware
    );
    $router->post(
        '/admin/items',
        static fn (Request $request): Response => $rentalItemController->store($request),
        $adminMiddleware
    );
    $router->get(
        '/admin/items/{public_id}/edit',
        static fn (Request $request): Response => $rentalItemController->edit($request),
        $adminMiddleware
    );
    $router->post(
        '/admin/items/{public_id}',
        static fn (Request $request): Response => $rentalItemController->update($request),
        $adminMiddleware
    );
    $router->get(
        '/admin/media/{public_id}/{variant}',
        static fn (Request $request): Response => $mediaDeliveryController->adminImage($request),
        $adminMiddleware
    );
    $router->post(
        '/admin/items/{public_id}/media',
        static fn (Request $request): Response => $rentalItemMediaController->store($request),
        $adminMiddleware
    );
    $router->post(
        '/admin/items/{public_id}/media/sort',
        static fn (Request $request): Response => $rentalItemMediaController->sort($request),
        $adminMiddleware
    );
    $router->post(
        '/admin/items/{public_id}/media/{media_public_id}/primary',
        static fn (Request $request): Response => $rentalItemMediaController->primary($request),
        $adminMiddleware
    );
    $router->post(
        '/admin/items/{public_id}/media/{media_public_id}/archive',
        static fn (Request $request): Response => $rentalItemMediaController->archive($request),
        $adminMiddleware
    );
    $router->get(
        '/admin/items/{public_id}/availability',
        static fn (Request $request): Response => $adminAvailabilityBlockController->index($request),
        $adminMiddleware
    );
    $router->get(
        '/admin/items/{public_id}/availability/create',
        static fn (Request $request): Response => $adminAvailabilityBlockController->create($request),
        $adminMiddleware
    );
    $router->post(
        '/admin/items/{public_id}/availability',
        static fn (Request $request): Response => $adminAvailabilityBlockController->store($request),
        $adminMiddleware
    );
    $router->post(
        '/admin/items/{public_id}/availability/{id}/archive',
        static fn (Request $request): Response => $adminAvailabilityBlockController->archive($request),
        $adminMiddleware
    );
    $router->get(
        '/admin/items/{public_id}/rates',
        static fn (Request $request): Response => $itemRateController->index($request),
        $adminMiddleware
    );
    $router->get(
        '/admin/items/{public_id}/rates/create',
        static fn (Request $request): Response => $itemRateController->create($request),
        $adminMiddleware
    );
    $router->post(
        '/admin/items/{public_id}/rates',
        static fn (Request $request): Response => $itemRateController->store($request),
        $adminMiddleware
    );
    $router->get(
        '/admin/items/{public_id}/rates/{id}/edit',
        static fn (Request $request): Response => $itemRateController->edit($request),
        $adminMiddleware
    );
    $router->post(
        '/admin/items/{public_id}/rates/{id}',
        static fn (Request $request): Response => $itemRateController->update($request),
        $adminMiddleware
    );
    $router->post(
        '/admin/items/{public_id}/rates/{id}/archive',
        static fn (Request $request): Response => $itemRateController->archive($request),
        $adminMiddleware
    );

    $router->get('/health', static fn (): Response => Response::json([
        'status' => 'ok',
    ]));
};
