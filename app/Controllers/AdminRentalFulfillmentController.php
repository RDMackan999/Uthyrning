<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\BookingException;
use App\Core\Collection;
use App\Core\CsrfTokenManager;
use App\Core\ModelException;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Http\RentalFulfillmentFormRequest;
use App\Models\Booking;
use App\Repositories\BookingItemRepository;
use App\Repositories\BookingRepository;
use App\Repositories\RentalFulfillmentItemRepository;
use App\Repositories\RentalFulfillmentRepository;
use App\Services\BookingStatusService;
use App\Services\OrganizationAuthorizationService;
use App\Services\RentalFulfillmentService;
use Throwable;

/**
 * Handles protected admin forms for rental handover and return.
 */
final class AdminRentalFulfillmentController extends BaseController
{
    private readonly CsrfTokenManager $csrfTokenManager;

    public function __construct(
        private readonly BookingRepository $bookingRepository = new BookingRepository(),
        private readonly BookingItemRepository $bookingItemRepository = new BookingItemRepository(),
        private readonly RentalFulfillmentRepository $fulfillmentRepository = new RentalFulfillmentRepository(),
        private readonly RentalFulfillmentItemRepository $fulfillmentItemRepository = new RentalFulfillmentItemRepository(),
        private readonly RentalFulfillmentService $fulfillmentService = new RentalFulfillmentService(),
        private readonly RentalFulfillmentFormRequest $formRequest = new RentalFulfillmentFormRequest(),
        private readonly OrganizationAuthorizationService $authorizationService = new OrganizationAuthorizationService(),
        ?CsrfTokenManager $csrfTokenManager = null,
    ) {
        parent::__construct();

        $this->csrfTokenManager = $csrfTokenManager ?? CsrfTokenManager::fromConfig();
    }

    /**
     * Create controller with shared repositories and services.
     */
    public static function fromConfig(): self
    {
        $bookingRepository = new BookingRepository();
        $bookingItemRepository = new BookingItemRepository();
        $fulfillmentRepository = new RentalFulfillmentRepository();
        $fulfillmentItemRepository = new RentalFulfillmentItemRepository();
        $authorizationService = new OrganizationAuthorizationService();
        $bookingStatusService = new BookingStatusService($bookingRepository);

        return new self(
            $bookingRepository,
            $bookingItemRepository,
            $fulfillmentRepository,
            $fulfillmentItemRepository,
            new RentalFulfillmentService(
                $bookingRepository,
                $bookingItemRepository,
                $fulfillmentRepository,
                $fulfillmentItemRepository,
                $bookingStatusService,
                $authorizationService
            ),
            new RentalFulfillmentFormRequest(),
            $authorizationService
        );
    }

    /**
     * Show handover form for an approved booking.
     */
    public function handover(Request $request): Response
    {
        $booking = $this->bookingFromRoute($request, 'handover');
        $bookingData = $booking->toArray();

        if (($bookingData['status_key'] ?? null) !== 'approved') {
            return $this->redirect($this->bookingPath($booking) . '?error=fulfillment_status');
        }

        if ($this->fulfillmentForBooking($bookingData) !== null) {
            return $this->redirect($this->bookingPath($booking) . '?error=fulfillment_exists');
        }

        return $this->renderHandover($request, $bookingData);
    }

    /**
     * Store handover facts through the fulfillment service.
     */
    public function storeHandover(Request $request): Response
    {
        $booking = $this->bookingFromRoute($request, 'handover');
        $bookingData = $booking->toArray();
        $postData = $this->postData($request);

        if (!$this->csrfTokenManager->validate($request, $this->stringValue($postData['csrf_token'] ?? null))) {
            return $this->renderHandover($request, $bookingData, $postData, ['csrf' => 'Formuläret kunde inte verifieras. Försök igen.']);
        }

        if (($bookingData['status_key'] ?? null) !== 'approved' || $this->fulfillmentForBooking($bookingData) !== null) {
            return $this->redirect($this->bookingPath($booking) . '?error=fulfillment_status');
        }

        $items = $this->bookingItems($bookingData);
        $validated = $this->formRequest->validateHandover($postData, $items);

        if ($validated['errors'] !== []) {
            return $this->renderHandover($request, $bookingData, $postData, $validated['errors']);
        }

        try {
            $this->fulfillmentService->recordHandover(
                $request,
                $this->stringValue($bookingData['public_id'] ?? null),
                $validated['data']
            );
        } catch (BookingException|ModelException) {
            return $this->renderHandover(
                $request,
                $bookingData,
                $postData,
                ['fulfillment' => 'Utlämningen kunde inte registreras för bokningens nuvarande läge.']
            );
        } catch (Throwable) {
            return $this->renderHandover(
                $request,
                $bookingData,
                $postData,
                ['fulfillment' => 'Utlämningen kunde inte sparas. Ladda om sidan och försök igen.']
            );
        }

        return $this->redirect($this->bookingPath($booking) . '?message=handover');
    }

    /**
     * Show return form for an active booking with existing handover.
     */
    public function returnForm(Request $request): Response
    {
        $booking = $this->bookingFromRoute($request, 'return');
        $bookingData = $booking->toArray();
        $fulfillment = $this->fulfillmentForBooking($bookingData);

        if (($bookingData['status_key'] ?? null) !== 'active' || $fulfillment === null || ($fulfillment['actual_return_at'] ?? null) !== null) {
            return $this->redirect($this->bookingPath($booking) . '?error=fulfillment_status');
        }

        return $this->renderReturn($request, $bookingData, $fulfillment);
    }

    /**
     * Store return facts through the fulfillment service.
     */
    public function storeReturn(Request $request): Response
    {
        $booking = $this->bookingFromRoute($request, 'return');
        $bookingData = $booking->toArray();
        $fulfillment = $this->fulfillmentForBooking($bookingData);
        $postData = $this->postData($request);

        if ($fulfillment === null) {
            return $this->redirect($this->bookingPath($booking) . '?error=fulfillment_status');
        }

        if (!$this->csrfTokenManager->validate($request, $this->stringValue($postData['csrf_token'] ?? null))) {
            return $this->renderReturn($request, $bookingData, $fulfillment, $postData, ['csrf' => 'Formuläret kunde inte verifieras. Försök igen.']);
        }

        if (($bookingData['status_key'] ?? null) !== 'active' || ($fulfillment['actual_return_at'] ?? null) !== null) {
            return $this->redirect($this->bookingPath($booking) . '?error=fulfillment_status');
        }

        $fulfillmentItems = $this->fulfillmentItemRepository->findForFulfillment((int) ($fulfillment['id'] ?? 0));
        $validated = $this->formRequest->validateReturn($postData, $fulfillmentItems);

        if ($validated['errors'] !== []) {
            return $this->renderReturn($request, $bookingData, $fulfillment, $postData, $validated['errors']);
        }

        try {
            $this->fulfillmentService->recordReturn(
                $request,
                $this->stringValue($bookingData['public_id'] ?? null),
                $validated['data']
            );
        } catch (BookingException|ModelException) {
            return $this->renderReturn(
                $request,
                $bookingData,
                $fulfillment,
                $postData,
                ['fulfillment' => 'Återlämningen kunde inte registreras för bokningens nuvarande läge.']
            );
        } catch (Throwable) {
            return $this->renderReturn(
                $request,
                $bookingData,
                $fulfillment,
                $postData,
                ['fulfillment' => 'Återlämningen kunde inte sparas. Ladda om sidan och försök igen.']
            );
        }

        return $this->redirect($this->bookingPath($booking) . '?message=return');
    }

    /**
     * Resolve route public_id to an admin-visible booking.
     */
    private function bookingFromRoute(Request $request, string $action): Booking
    {
        $publicId = $this->stringValue($request->route('public_id'));

        if ($publicId === '') {
            throw new NotFoundException();
        }

        $booking = $this->bookingRepository->findAdminByPublicId($publicId);

        if ($booking === null) {
            throw new NotFoundException();
        }

        $bookingData = $booking->toArray();
        $this->authorizationService->assertCanAccessResource(
            $request,
            (int) ($bookingData['organization_id'] ?? 0),
            'booking',
            (int) ($bookingData['id'] ?? 0),
            $action
        );

        return $booking;
    }

    /**
     * @param array<string, mixed> $booking
     */
    private function renderHandover(
        Request $request,
        array $booking,
        array $data = [],
        array $errors = []
    ): Response {
        return $this->viewWithLayout('admin/bookings/handover', 'layouts/admin', [
            'pageTitle' => 'Lämna ut bokning',
            'booking' => $booking,
            'items' => $this->bookingItems($booking)->toArray(),
            'data' => $this->handoverDefaults($booking, $data),
            'errors' => $errors,
            'conditionOptions' => RentalFulfillmentFormRequest::conditionOptions(),
            'depositOptions' => RentalFulfillmentFormRequest::depositOptions(),
            'csrfToken' => $this->csrfTokenManager->generateToken($request),
        ]);
    }

    /**
     * @param array<string, mixed> $booking
     * @param array<string, mixed> $fulfillment
     */
    private function renderReturn(
        Request $request,
        array $booking,
        array $fulfillment,
        array $data = [],
        array $errors = []
    ): Response {
        return $this->viewWithLayout('admin/bookings/return', 'layouts/admin', [
            'pageTitle' => 'Registrera återlämning',
            'booking' => $booking,
            'fulfillment' => $fulfillment,
            'fulfillmentItems' => $this->fulfillmentItemRepository
                ->findAdminForFulfillment((int) ($fulfillment['id'] ?? 0)),
            'data' => $this->returnDefaults($fulfillment, $data),
            'errors' => $errors,
            'conditionOptions' => RentalFulfillmentFormRequest::conditionOptions(),
            'depositOptions' => RentalFulfillmentFormRequest::depositOptions(),
            'csrfToken' => $this->csrfTokenManager->generateToken($request),
            'isLateReturn' => $this->isLateReturn(
                $this->stringValue($booking['end_date'] ?? null),
                $this->stringValue($data['actual_return_at'] ?? $this->nowUtc())
            ),
        ]);
    }

    /**
     * @param array<string, mixed> $booking
     */
    private function bookingItems(array $booking): Collection
    {
        return $this->bookingItemRepository->findAdminForBooking(
            (int) ($booking['organization_id'] ?? 0),
            (int) ($booking['id'] ?? 0)
        );
    }

    /**
     * @param array<string, mixed> $booking
     * @return array<string, mixed>|null
     */
    private function fulfillmentForBooking(array $booking): ?array
    {
        return $this->fulfillmentRepository->findAdminByBookingId(
            (int) ($booking['organization_id'] ?? 0),
            (int) ($booking['id'] ?? 0)
        );
    }

    /**
     * @param array<string, mixed> $booking
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function handoverDefaults(array $booking, array $data): array
    {
        return $data + [
            'actual_handover_at' => $this->nowUtc(),
            'received_by_name' => $this->stringValue($booking['customer_name'] ?? null),
            'deposit_received_amount' => $booking['deposit_amount'] ?? null,
            'deposit_status_key' => (float) ($booking['deposit_amount'] ?? 0) > 0 ? 'required' : 'not_required',
            'terms_version_key' => 'v1',
            'handover_note' => null,
        ];
    }

    /**
     * @param array<string, mixed> $fulfillment
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function returnDefaults(array $fulfillment, array $data): array
    {
        return $data + [
            'actual_return_at' => $this->nowUtc(),
            'deposit_returned_amount' => null,
            'deposit_retained_amount' => null,
            'deposit_status_key' => $fulfillment['deposit_status_key'] ?? 'not_required',
            'return_note' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function postData(Request $request): array
    {
        $postData = $request->post();

        return is_array($postData) ? $postData : [];
    }

    private function bookingPath(Booking $booking): string
    {
        return '/admin/bookings/' . rawurlencode((string) ($booking->toArray()['public_id'] ?? ''));
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) || is_numeric($value) ? trim((string) $value) : '';
    }

    private function nowUtc(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    private function isLateReturn(string $plannedEndDate, string $actualReturnAt): bool
    {
        return $plannedEndDate !== '' && substr($actualReturnAt, 0, 10) > $plannedEndDate;
    }
}
