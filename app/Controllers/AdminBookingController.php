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
use App\Services\BookingStatusService;
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
        private readonly BookingStatusService $bookingStatusService = new BookingStatusService(),
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
            new BookingStatusService($bookingRepository)
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
            'bookings' => $this->bookingRepository->findAllForAdmin($statusFilter)->toArray(),
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

        return $this->viewWithLayout('admin/bookings/show', 'layouts/admin', [
            'pageTitle' => 'Bokningsdetalj',
            'booking' => $bookingData,
            'items' => $this->bookingItemRepository->findAdminForBooking($organizationId, $bookingId)->toArray(),
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
        return $this->transition($request, 'active');
    }

    /**
     * Mark an active booking as completed.
     */
    public function complete(Request $request): Response
    {
        return $this->transition($request, 'completed');
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
            'active' => 'Markera som aktiv',
            'completed' => 'Markera som slutförd',
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
            default => null,
        };
    }

    private function error(Request $request): ?string
    {
        return match ($request->query('error')) {
            'csrf' => 'Formuläret kunde inte verifieras. Försök igen.',
            'transition' => 'Statusändringen är inte tillåten för bokningens nuvarande status.',
            'changed' => 'Bokningen kunde inte uppdateras. Ladda om sidan och försök igen.',
            default => null,
        };
    }
}
