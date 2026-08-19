<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\BookingException;
use App\Core\Database;
use App\Core\Logger;
use App\Models\Booking;
use App\Repositories\BookingRepository;
use Throwable;

/**
 * Owns booking status transition rules for Version 1.
 */
final class BookingStatusService
{
    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        'request' => ['approved', 'rejected', 'cancelled'],
        'approved' => ['active', 'cancelled'],
        'active' => ['completed', 'cancelled'],
    ];

    /**
     * @var array<string, string>
     */
    private const AUDIT_EVENTS = [
        'approved' => 'booking_approved',
        'rejected' => 'booking_rejected',
        'cancelled' => 'booking_cancelled',
        'active' => 'booking_started',
        'completed' => 'booking_completed',
    ];

    public function __construct(
        private readonly BookingRepository $bookingRepository = new BookingRepository(),
        private readonly AuditService $auditService = new AuditService(),
        private readonly NotificationService $notificationService = new NotificationService()
    ) {
    }

    /**
     * Determine whether a transition is allowed by the Version 1 state machine.
     */
    public function canTransition(string $fromStatusKey, string $toStatusKey, ?string $comment = null): bool
    {
        $from = $this->normalizeStatus($fromStatusKey);
        $to = $this->normalizeStatus($toStatusKey);

        if (!in_array($to, self::ALLOWED_TRANSITIONS[$from] ?? [], true)) {
            return false;
        }

        return $from !== 'active' || $to !== 'cancelled' || trim((string) $comment) !== '';
    }

    /**
     * Persist a status transition and append status history.
     */
    public function transition(
        int $organizationId,
        int $bookingId,
        string $toStatusKey,
        ?int $actorUserId = null,
        ?string $comment = null
    ): Booking {
        $booking = $this->bookingRepository->findById($bookingId, $organizationId);
        $bookingData = $booking->toArray();
        $fromStatusKey = (string) ($bookingData['status_key'] ?? '');
        $toStatusKey = $this->normalizeStatus($toStatusKey);

        if (!$this->canTransition($fromStatusKey, $toStatusKey, $comment)) {
            throw new BookingException('Booking status transition is not allowed.');
        }

        $pdo = Database::pdo();
        $startedTransaction = !$pdo->inTransaction();

        try {
            if ($startedTransaction) {
                $pdo->beginTransaction();
            }

            $updated = $this->bookingRepository->updateStatus(
                $organizationId,
                $bookingId,
                $toStatusKey,
                $actorUserId,
                $comment
            );
            $updatedData = $updated->toArray();
            $this->auditService->record(
                self::AUDIT_EVENTS[$toStatusKey] ?? 'booking_status_changed',
                $actorUserId,
                'booking',
                (int) ($updatedData['id'] ?? $bookingId),
                null,
                null,
                [
                    'organization_id' => $organizationId,
                    'from_status_key' => $fromStatusKey,
                    'to_status_key' => $toStatusKey,
                ]
            );

            if ($startedTransaction) {
                $pdo->commit();
            }

            $this->notifyStatusChanged($updated, self::AUDIT_EVENTS[$toStatusKey] ?? null);

            return $updated;
        } catch (Throwable $exception) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function notifyStatusChanged(Booking $booking, ?string $eventKey): void
    {
        if ($eventKey === null || !in_array($eventKey, [
            'booking_approved',
            'booking_rejected',
            'booking_cancelled',
        ], true)) {
            return;
        }

        try {
            $this->notificationService->notifyBookingStatusChanged($booking, $eventKey);
        } catch (Throwable) {
            $this->logNotificationFailure('Booking status notification failed.');
        }
    }

    private function logNotificationFailure(string $message): void
    {
        try {
            (new Logger(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs'))
                ->warning($message);
        } catch (Throwable) {
        }
    }

    private function normalizeStatus(string $statusKey): string
    {
        return strtolower(trim($statusKey));
    }
}
