<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\NotificationException;
use App\Models\Booking;
use App\Models\Notification;
use App\Repositories\NotificationRepository;

/**
 * Creates booking notifications from domain events.
 */
final class NotificationService
{
    /**
     * @var array<string, string>
     */
    private const CUSTOMER_TEMPLATES = [
        'booking_created' => 'booking_request_received_customer',
        'booking_approved' => 'booking_approved_customer',
        'booking_rejected' => 'booking_rejected_customer',
        'booking_cancelled' => 'booking_cancelled_customer',
    ];

    public function __construct(
        private readonly NotificationRepository $notificationRepository = new NotificationRepository(),
        private readonly NotificationDispatcher $dispatcher = new NotificationDispatcher(),
        private readonly NotificationTemplateService $templateService = new NotificationTemplateService(),
        private readonly AuditService $auditService = new AuditService(),
    ) {
    }

    /**
     * Create customer and admin/renter notifications for a new booking request.
     *
     * @return list<Notification>
     */
    public function notifyBookingCreated(Booking $booking): array
    {
        $context = $this->contextForBooking($booking);
        $notifications = [
            $this->createAndDispatch($context, 'booking_created', 'customer', (string) $context['customer_email']),
        ];
        $adminRecipient = $this->adminRecipient($context);

        if ($adminRecipient !== null) {
            $notifications[] = $this->createAndDispatch($context, 'booking_created', 'admin', $adminRecipient);
        }

        return $notifications;
    }

    /**
     * Create a customer notification for supported status events.
     */
    public function notifyBookingStatusChanged(Booking $booking, string $eventKey): ?Notification
    {
        $eventKey = $this->normalizeEvent($eventKey);

        if (!array_key_exists($eventKey, self::CUSTOMER_TEMPLATES) || $eventKey === 'booking_created') {
            return null;
        }

        $context = $this->contextForBooking($booking);

        return $this->createAndDispatch($context, $eventKey, 'customer', (string) $context['customer_email']);
    }

    /**
     * Build a stable idempotency key for one logical notification.
     */
    public function idempotencyKey(
        int $bookingId,
        string $eventKey,
        string $channelKey,
        string $recipientEmail,
        string $recipientType = ''
    ): string {
        return hash('sha256', implode('|', [
            'booking',
            $bookingId,
            $this->normalizeEvent($eventKey),
            strtolower(trim($channelKey)),
            strtolower(trim($recipientEmail)),
            strtolower(trim($recipientType)),
        ]));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function createAndDispatch(
        array $context,
        string $eventKey,
        string $recipientType,
        string $recipientEmail
    ): Notification {
        $eventKey = $this->normalizeEvent($eventKey);
        $recipientEmail = $this->normalizeEmail($recipientEmail);
        $bookingId = (int) ($context['id'] ?? 0);
        $templateKey = $recipientType === 'admin'
            ? 'new_booking_request_admin'
            : (self::CUSTOMER_TEMPLATES[$eventKey] ?? '');

        if ($templateKey === '') {
            throw NotificationException::unsupportedEvent($eventKey);
        }

        $result = $this->notificationRepository->createIdempotent([
            'organization_id' => (int) ($context['organization_id'] ?? 0),
            'booking_id' => $bookingId,
            'event_key' => $eventKey,
            'channel_key' => 'email',
            'recipient_type' => $recipientType,
            'recipient_email' => $recipientEmail,
            'template_key' => $templateKey,
            'subject' => $this->templateService->subject($templateKey),
            'status_key' => 'pending',
            'idempotency_key' => $this->idempotencyKey($bookingId, $eventKey, 'email', $recipientEmail, $recipientType),
            'max_attempts' => 3,
        ]);

        $notification = $result['notification'];
        $notificationData = $notification->toArray();

        if ($result['created']) {
            $this->auditService->record('notification_created', null, 'notification', (int) ($notificationData['id'] ?? 0), null, null, [
                'organization_id' => (int) ($context['organization_id'] ?? 0),
                'booking_id' => $bookingId,
                'event_key' => $eventKey,
                'channel_key' => 'email',
                'recipient_type' => $recipientType,
            ]);
        }

        return $this->dispatcher->dispatch($notification, $context);
    }

    /**
     * @return array<string, mixed>
     */
    private function contextForBooking(Booking $booking): array
    {
        $bookingData = $booking->toArray();
        $organizationId = (int) ($bookingData['organization_id'] ?? 0);
        $bookingId = (int) ($bookingData['id'] ?? 0);

        if ($organizationId <= 0 || $bookingId <= 0) {
            throw new NotificationException('Booking notification context is incomplete.');
        }

        $context = $this->notificationRepository->bookingContext($organizationId, $bookingId);
        $customerEmail = (string) ($context['customer_email_normalized'] ?? $context['customer_email'] ?? '');
        $context['customer_email'] = $this->normalizeEmail($customerEmail);
        $context['status_message'] = $this->statusMessage((string) ($context['status_key'] ?? ''));

        return $context;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function adminRecipient(array $context): ?string
    {
        return $this->notificationRepository->findOrganizationNotificationEmail((int) ($context['organization_id'] ?? 0));
    }

    private function normalizeEvent(string $eventKey): string
    {
        $normalized = strtolower(trim($eventKey));

        if (!in_array($normalized, ['booking_created', 'booking_approved', 'booking_rejected', 'booking_cancelled'], true)) {
            throw NotificationException::unsupportedEvent($normalized);
        }

        return $normalized;
    }

    private function normalizeEmail(string $email): string
    {
        $normalized = strtolower(trim($email));

        if (str_contains($normalized, "\r") || str_contains($normalized, "\n") || !filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            throw NotificationException::invalidRecipient();
        }

        return $normalized;
    }

    private function statusMessage(string $statusKey): string
    {
        return match ($statusKey) {
            'approved' => 'Bokningen är godkänd.',
            'rejected' => 'Bokningsförfrågan är nekad.',
            'cancelled' => 'Bokningen är avbokad.',
            default => 'Bokningsförfrågan väntar på granskning.',
        };
    }
}
