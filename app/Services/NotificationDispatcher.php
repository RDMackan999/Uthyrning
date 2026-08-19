<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\EmailTransportInterface;
use App\Core\NotificationException;
use App\Models\Notification;
use App\Repositories\NotificationAttemptRepository;
use App\Repositories\NotificationRepository;
use App\Services\Email\EmailTransportFactory;
use App\Services\Email\EmailMessage;
use Throwable;

/**
 * Dispatches pending notifications through the configured transport.
 */
final class NotificationDispatcher
{
    public function __construct(
        private readonly NotificationRepository $notificationRepository = new NotificationRepository(),
        private readonly NotificationAttemptRepository $attemptRepository = new NotificationAttemptRepository(),
        private readonly NotificationTemplateService $templateService = new NotificationTemplateService(),
        private readonly AuditService $auditService = new AuditService(),
        private readonly ?EmailTransportInterface $transport = null,
        private readonly EmailTransportFactory $transportFactory = new EmailTransportFactory(),
    ) {
    }

    /**
     * Render and deliver one notification if it is still pending.
     *
     * @param array<string, mixed> $context
     */
    public function dispatch(Notification $notification, array $context): Notification
    {
        $data = $notification->toArray();
        $notificationId = (int) ($data['id'] ?? 0);
        $statusKey = (string) ($data['status_key'] ?? '');
        $attemptsCount = (int) ($data['attempts_count'] ?? 0);
        $maxAttempts = (int) ($data['max_attempts'] ?? 3);

        if ($notificationId <= 0) {
            throw new NotificationException('Notification id is missing.');
        }

        if ($statusKey === 'sent' || $attemptsCount >= $maxAttempts) {
            return $notification;
        }

        try {
            $rendered = $this->templateService->render((string) ($data['template_key'] ?? ''), $context);
            $message = new EmailMessage(
                (string) ($data['recipient_email'] ?? ''),
                (string) ($data['subject'] ?? ''),
                $rendered['html'],
                $rendered['text']
            );
            $result = $this->emailTransport()->send($message);
        } catch (NotificationException $exception) {
            return $this->recordFailure(
                $notificationId,
                $exception->safeErrorCode(),
                'Email delivery failed.',
                $attemptsCount,
                $maxAttempts
            );
        } catch (Throwable $exception) {
            return $this->recordFailure($notificationId, 'delivery_exception', $exception::class, $attemptsCount, $maxAttempts);
        }

        if ($result->isSuccessful()) {
            $attempt = $this->attemptRepository->createAttempt($notificationId, $this->transportKey(), 'sent');
            $updated = $this->notificationRepository->markSent(
                $notificationId,
                (int) ($attempt->toArray()['attempt_number'] ?? ($attemptsCount + 1))
            );
            $this->auditService->record('notification_sent', null, 'notification', $notificationId, null, null, [
                'organization_id' => (int) ($data['organization_id'] ?? 0),
                'booking_id' => $this->nullableInt($data['booking_id'] ?? null),
                'event_key' => (string) ($data['event_key'] ?? ''),
                'channel_key' => (string) ($data['channel_key'] ?? ''),
                'recipient_type' => (string) ($data['recipient_type'] ?? ''),
            ]);

            return $updated;
        }

        return $this->recordFailure(
            $notificationId,
            $result->errorCode() ?? 'delivery_failed',
            $result->errorSummary() ?? 'Email delivery failed.',
            $attemptsCount,
            $maxAttempts
        );
    }

    private function recordFailure(
        int $notificationId,
        string $errorCode,
        string $errorSummary,
        int $currentAttempts,
        int $maxAttempts
    ): Notification {
        $attempt = $this->attemptRepository->createAttempt(
            $notificationId,
            $this->transportKey(),
            'failed',
            $errorCode,
            $errorSummary
        );
        $attemptNumber = (int) ($attempt->toArray()['attempt_number'] ?? ($currentAttempts + 1));
        $canRetry = $attemptNumber < $maxAttempts;
        $updated = $this->notificationRepository->markFailed(
            $notificationId,
            $attemptNumber,
            $errorCode,
            $errorSummary,
            $canRetry
        );
        $updatedData = $updated->toArray();
        $this->auditService->record('notification_failed', null, 'notification', $notificationId, null, null, [
            'organization_id' => (int) ($updatedData['organization_id'] ?? 0),
            'booking_id' => $this->nullableInt($updatedData['booking_id'] ?? null),
            'event_key' => (string) ($updatedData['event_key'] ?? ''),
            'channel_key' => (string) ($updatedData['channel_key'] ?? ''),
            'recipient_type' => (string) ($updatedData['recipient_type'] ?? ''),
            'error_code' => substr(trim($errorCode), 0, 100),
            'can_retry' => $canRetry,
        ]);

        return $updated;
    }

    private function emailTransport(): EmailTransportInterface
    {
        if ($this->transport !== null) {
            return $this->transport;
        }

        return $this->transportFactory->make();
    }

    private function transportKey(): string
    {
        return $this->transportFactory->transportKey();
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
