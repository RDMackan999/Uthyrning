<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\BookingException;
use App\Core\CsrfTokenManager;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Booking;
use App\Repositories\BookingItemRepository;
use App\Repositories\BookingRepository;
use App\Repositories\RentalFulfillmentItemRepository;
use App\Repositories\RentalFulfillmentRepository;
use App\Services\BookingStatusService;
use App\Services\OrganizationAuthorizationService;
use Throwable;

/**
 * Handles protected administrative booking management.
 */
final class AdminBookingController extends BaseController
{
    /**
     * @var list<string>
     */
    private const STATUS_KEYS = ['request', 'approved', 'rejected', 'cancelled', 'active', 'completed'];

    private readonly CsrfTokenManager $csrfTokenManager;

    public function __construct(
        private readonly BookingRepository $bookingRepository = new BookingRepository(),
        private readonly BookingItemRepository $bookingItemRepository = new BookingItemRepository(),
        private readonly RentalFulfillmentRepository $fulfillmentRepository = new RentalFulfillmentRepository(),
        private readonly RentalFulfillmentItemRepository $fulfillmentItemRepository = new RentalFulfillmentItemRepository(),
        private readonly BookingStatusService $bookingStatusService = new BookingStatusService(),
        private readonly OrganizationAuthorizationService $authorizationService = new OrganizationAuthorizationService(),
        ?CsrfTokenManager $csrfTokenManager = null,
    ) {
        parent::__construct();

        $this->csrfTokenManager = $csrfTokenManager ?? CsrfTokenManager::fromConfig();
    }

    /**
     * Create controller with configured CSRF storage.
     */
    public static function fromConfig(): self
    {
        $bookingRepository = new BookingRepository();

        return new self(
            $bookingRepository,
            new BookingItemRepository(),
            new RentalFulfillmentRepository(),
            new RentalFulfillmentItemRepository(),
            new BookingStatusService($bookingRepository),
            new OrganizationAuthorizationService()
        );
    }

    /**
     * Show non-deleted bookings for administration.
     */
    public function index(Request $request): Response
    {
        $statusFilter = $this->statusFilter($request->query('status'));

        return $this->viewWithLayout('admin/bookings/index', 'layouts/admin', [
            'pageTitle' => 'Bokningar',
            'bookings' => $this->bookingRepository
                ->findAllForAdmin($statusFilter, $this->authorizationService->organizationScopeForRequest($request))
                ->toArray(),
            'statusFilter' => $statusFilter,
            'statusOptions' => $this->statusOptions(),
            'csrfToken' => $this->csrfTokenManager->generateToken($request),
            'message' => $this->message($request),
        ]);
    }

    /**
     * Show one booking with items, snapshots, internal notes and status history.
     */
    public function show(Request $request): Response
    {
        $booking = $this->bookingFromRoute($request);
        $bookingData = $booking->toArray();
        $organizationId = (int) ($bookingData['organization_id'] ?? 0);
        $bookingId = (int) ($bookingData['id'] ?? 0);
        $statusKey = $this->stringValue($bookingData['status_key'] ?? null);
        $fulfillment = $this->fulfillmentRepository->findAdminByBookingId($organizationId, $bookingId);
        $fulfillmentId = (int) ($fulfillment['id'] ?? 0);

        return $this->viewWithLayout('admin/bookings/show', 'layouts/admin', [
            'pageTitle' => 'Bokningsdetalj',
            'booking' => $bookingData,
            'items' => $this->bookingItemRepository->findAdminForBooking($organizationId, $bookingId)->toArray(),
            'fulfillment' => $fulfillment,
            'fulfillmentItems' => $fulfillmentId > 0
                ? $this->fulfillmentItemRepository->findAdminForFulfillment($fulfillmentId)
                : [],
            'fulfillmentAction' => $this->fulfillmentAction($statusKey, $fulfillment),
            'statusHistory' => $this->bookingRepository->findStatusHistoryForBooking($organizationId, $bookingId),
            'internalNotes' => $this->bookingRepository->findInternalNotesForBooking($organizationId, $bookingId),
            'availableActions' => $this->availableActions($statusKey),
            'csrfToken' => $this->csrfTokenManager->generateToken($request),
            'message' => $this->message($request),
            'error' => $this->error($request),
        ]);
    }

    /**
     * Approve a booking request.
     */
    public function approve(Request $request): Response
    {
        return $this->transition($request, 'approved');
    }

    /**
     * Reject a booking request.
     */
    public function reject(Request $request): Response
    {
        return $this->transition($request, 'rejected');
    }

    /**
     * Cancel a request, approved or active booking.
     */
    public function cancel(Request $request): Response
    {
        return $this->transition($request, 'cancelled', 'Administrativ avbokning via admin.');
    }

    /**
     * Mark an approved booking as active.
     */
    public function start(Request $request): Response
    {
        return $this->redirect($this->bookingPath($this->bookingFromRoute($request)) . '?error=fulfillment_required');
    }

    /**
     * Mark an active booking as completed.
     */
    public function complete(Request $request): Response
    {
        return $this->redirect($this->bookingPath($this->bookingFromRoute($request)) . '?error=fulfillment_required');
    }

    /**
     * Persist a status transition through the booking status domain service.
     */
    private function transition(Request $request, string $toStatusKey, ?string $defaultComment = null): Response
    {
        $booking = $this->bookingFromRoute($request);
        $bookingData = $booking->toArray();
        $postData = $this->postData($request);
        $path = $this->bookingPath($booking);

        if (!$this->csrfTokenManager->validate($request, $this->stringValue($postData['csrf_token'] ?? null))) {
            return $this->redirect($path . '?error=csrf');
        }

        try {
            $this->bookingStatusService->transition(
                (int) ($bookingData['organization_id'] ?? 0),
                (int) ($bookingData['id'] ?? 0),
                $toStatusKey,
                $request->authenticatedUserId(),
                $this->statusComment($postData, $defaultComment)
            );
        } catch (BookingException) {
            return $this->redirect($path . '?error=transition');
        } catch (Throwable) {
            return $this->redirect($path . '?error=changed');
        }

        return $this->redirect($path . '?message=' . rawurlencode($toStatusKey));
    }

    /**
     * Resolve route public_id to an admin-visible booking.
     */
    private function bookingFromRoute(Request $request): Booking
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
            'manage'
        );

        return $booking;
    }

    /**
     * @param array<string, mixed> $postData
     */
    private function statusComment(array $postData, ?string $defaultComment): ?string
    {
        $comment = $this->stringValue($postData['status_comment'] ?? null);

        if ($comment !== '') {
            return $comment;
        }

        return $defaultComment;
    }

    /**
     * @return array<string, string>
     */
    private function availableActions(string $statusKey): array
    {
        $actions = [
            'approved' => 'Godkänn',
            'rejected' => 'Neka',
            'cancelled' => 'Avboka',
        ];
        $available = [];

        foreach ($actions as $targetStatus => $label) {
            $comment = $targetStatus === 'cancelled' ? 'Administrativ avbokning via admin.' : null;

            if ($this->bookingStatusService->canTransition($statusKey, $targetStatus, $comment)) {
                $available[$targetStatus] = $label;
            }
        }

        return $available;
    }

    /**
     * @param array<string, mixed>|null $fulfillment
     * @return array{path: string, label: string}|null
     */
    private function fulfillmentAction(string $statusKey, ?array $fulfillment): ?array
    {
        if ($statusKey === 'approved' && $fulfillment === null) {
            return [
                'path' => 'handover',
                'label' => 'Lämna ut',
            ];
        }

        if ($statusKey === 'active' && $fulfillment !== null && ($fulfillment['actual_return_at'] ?? null) === null) {
            return [
                'path' => 'return',
                'label' => 'Registrera återlämning',
            ];
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function statusOptions(): array
    {
        return [
            'request' => 'Förfrågan',
            'approved' => 'Godkänd',
            'rejected' => 'Nekad',
            'cancelled' => 'Avbokad',
            'active' => 'Aktiv',
            'completed' => 'Slutförd',
        ];
    }

    private function statusFilter(mixed $value): ?string
    {
        $statusKey = $this->stringValue($value);

        return in_array($statusKey, self::STATUS_KEYS, true) ? $statusKey : null;
    }

    private function bookingPath(Booking $booking): string
    {
        return '/admin/bookings/' . rawurlencode((string) ($booking->toArray()['public_id'] ?? ''));
    }

    /**
     * @return array<string, mixed>
     */
    private function postData(Request $request): array
    {
        $postData = $request->post();

        return is_array($postData) ? $postData : [];
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) || is_numeric($value) ? trim((string) $value) : '';
    }

    private function message(Request $request): ?string
    {
        return match ($request->query('message')) {
            'approved' => 'Bokningen har godkänts.',
            'rejected' => 'Bokningen har nekats.',
            'cancelled' => 'Bokningen har avbokats.',
            'active' => 'Bokningen har markerats som aktiv.',
            'completed' => 'Bokningen har markerats som slutförd.',
            'handover' => 'Utlämningen har registrerats.',
            'return' => 'Återlämningen har registrerats.',
            default => null,
        };
    }

    private function error(Request $request): ?string
    {
        return match ($request->query('error')) {
            'csrf' => 'Formuläret kunde inte verifieras. Försök igen.',
            'transition' => 'Statusändringen är inte tillåten för bokningens nuvarande status.',
            'changed' => 'Bokningen kunde inte uppdateras. Ladda om sidan och försök igen.',
            'fulfillment_required' => 'Bokningen måste hanteras via utlämnings- eller återlämningsflödet.',
            'fulfillment_status' => 'Åtgärden är inte tillåten för bokningens nuvarande genomförandeläge.',
            'fulfillment_exists' => 'Utlämning är redan registrerad för bokningen.',
            default => null,
        };
    }
}
