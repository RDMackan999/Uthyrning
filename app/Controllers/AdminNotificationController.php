<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\CsrfTokenManager;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Notification;
use App\Repositories\NotificationAttemptRepository;
use App\Repositories\NotificationRepository;
use App\Services\AuditService;
use App\Services\NotificationDispatcher;
use App\Services\NotificationService;
use App\Services\NotificationTemplateService;
use Throwable;

/**
 * Handles protected operational notification administration.
 */
final class AdminNotificationController extends BaseController
{
    private readonly CsrfTokenManager $csrfTokenManager;

    public function __construct(
        private readonly NotificationRepository $notificationRepository = new NotificationRepository(),
        private readonly NotificationAttemptRepository $attemptRepository = new NotificationAttemptRepository(),
        private readonly NotificationDispatcher $dispatcher = new NotificationDispatcher(),
        private readonly NotificationService $notificationService = new NotificationService(),
        private readonly AuditService $auditService = new AuditService(),
        ?CsrfTokenManager $csrfTokenManager = null,
    ) {
        parent::__construct();

        $this->csrfTokenManager = $csrfTokenManager ?? CsrfTokenManager::fromConfig();
    }

    /**
     * Create controller with configured dependencies.
     */
    public static function fromConfig(): self
    {
        $notificationRepository = new NotificationRepository();
        $attemptRepository = new NotificationAttemptRepository();
        $templateService = new NotificationTemplateService();
        $auditService = new AuditService();
        $dispatcher = new NotificationDispatcher(
            $notificationRepository,
            $attemptRepository,
            $templateService,
            $auditService
        );

        return new self(
            $notificationRepository,
            $attemptRepository,
            $dispatcher,
            new NotificationService($notificationRepository, $dispatcher, $templateService, $auditService),
            $auditService
        );
    }

    /**
     * Show notification list with simple safe filters.
     */
    public function index(Request $request): Response
    {
        $statusFilter = $this->statusFilter($request->query('status'));
        $eventFilter = $this->eventFilter($request->query('event'));

        return $this->viewWithLayout('admin/notifications/index', 'layouts/admin', [
            'pageTitle' => 'Notifieringar',
            'notifications' => $this->withMaskedRecipients(
                $this->notificationRepository->findAllForAdmin($statusFilter, $eventFilter)
            ),
            'statusFilter' => $statusFilter,
            'eventFilter' => $eventFilter,
            'statusOptions' => NotificationRepository::statusOptions(),
            'eventOptions' => NotificationRepository::eventOptions(),
            'message' => $this->message($request),
            'error' => $this->error($request),
        ]);
    }

    /**
     * Show one notification and append-only attempt history.
     */
    public function show(Request $request): Response
    {
        $notification = $this->notificationFromRoute($request);
        $notificationData = $notification->toArray();
        $notificationId = (int) ($notificationData['id'] ?? 0);
        $adminData = $this->notificationRepository->findAdminByPublicId(
            (string) ($notificationData['public_id'] ?? '')
        );

        if ($adminData === null) {
            throw new NotFoundException();
        }

        return $this->viewWithLayout('admin/notifications/show', 'layouts/admin', [
            'pageTitle' => 'Notifieringsdetalj',
            'notification' => $adminData,
            'attempts' => $this->attemptRepository->findForNotification($notificationId)->toArray(),
            'isRetryable' => $this->notificationRepository->isRetryable($notification),
            'csrfToken' => $this->csrfTokenManager->generateToken($request),
            'message' => $this->message($request),
            'error' => $this->error($request),
        ]);
    }

    /**
     * Retry one failed or pending notification through the existing dispatcher.
     */
    public function retry(Request $request): Response
    {
        $notification = $this->notificationFromRoute($request);
        $notificationData = $notification->toArray();
        $path = $this->notificationPath($notification);
        $postData = $this->postData($request);

        if (!$this->csrfTokenManager->validate($request, $this->stringValue($postData['csrf_token'] ?? null))) {
            return $this->redirect($path . '?error=csrf');
        }

        if (!$this->notificationRepository->isRetryable($notification)) {
            $attemptsCount = (int) ($notificationData['attempts_count'] ?? 0);
            $maxAttempts = (int) ($notificationData['max_attempts'] ?? 3);
            $errorCode = $attemptsCount >= $maxAttempts ? 'max_attempts' : 'not_retryable';

            return $this->redirect($path . '?error=' . $errorCode);
        }

        try {
            $context = $this->notificationService->contextForExistingNotification($notification);
            $this->auditRetry($request, $notification);
            $updated = $this->dispatcher->dispatch($notification, $context);
        } catch (Throwable) {
            return $this->redirect($path . '?error=transport');
        }

        $updatedData = $updated->toArray();
        $message = ($updatedData['status_key'] ?? null) === 'sent' ? 'retry_sent' : 'retry_failed';

        return $this->redirect($path . '?message=' . $message);
    }

    /**
     * Resolve route public_id to a notification.
     */
    private function notificationFromRoute(Request $request): Notification
    {
        $publicId = $this->stringValue($request->route('public_id'));

        if ($publicId === '') {
            throw new NotFoundException();
        }

        $notification = $this->notificationRepository->findByPublicId($publicId);

        if ($notification === null) {
            throw new NotFoundException();
        }

        return $notification;
    }

    /**
     * Record the manual retry operation without body, credentials or full recipient.
     */
    private function auditRetry(Request $request, Notification $notification): void
    {
        $data = $notification->toArray();

        $this->auditService->record(
            'notification_retried',
            $request->authenticatedUserId(),
            'notification',
            (int) ($data['id'] ?? 0),
            $request->ipAddress(),
            $request->userAgent(),
            [
                'organization_id' => (int) ($data['organization_id'] ?? 0),
                'booking_id' => $this->nullableInt($data['booking_id'] ?? null),
                'event_key' => (string) ($data['event_key'] ?? ''),
                'channel_key' => (string) ($data['channel_key'] ?? ''),
                'recipient_type' => (string) ($data['recipient_type'] ?? ''),
                'attempts_before' => (int) ($data['attempts_count'] ?? 0),
            ]
        );
    }

    /**
     * @param list<array<string, mixed>> $notifications
     * @return list<array<string, mixed>>
     */
    private function withMaskedRecipients(array $notifications): array
    {
        return array_map(function (array $notification): array {
            $notification['recipient_display'] = $this->maskedEmail(
                $this->stringValue($notification['recipient_email'] ?? null)
            );
            unset($notification['recipient_email']);

            return $notification;
        }, $notifications);
    }

    /**
     * @return array<string, mixed>
     */
    private function postData(Request $request): array
    {
        $postData = $request->post();

        return is_array($postData) ? $postData : [];
    }

    private function statusFilter(mixed $value): ?string
    {
        $statusKey = $this->stringValue($value);

        return array_key_exists($statusKey, NotificationRepository::statusOptions()) ? $statusKey : null;
    }

    private function eventFilter(mixed $value): ?string
    {
        $eventKey = $this->stringValue($value);

        return array_key_exists($eventKey, NotificationRepository::eventOptions()) ? $eventKey : null;
    }

    private function notificationPath(Notification $notification): string
    {
        return '/admin/notifications/' . rawurlencode((string) ($notification->toArray()['public_id'] ?? ''));
    }

    private function message(Request $request): ?string
    {
        return match ($request->query('message')) {
            'retry_sent' => 'Notifieringen har skickats igen.',
            'retry_failed' => 'Ett nytt försök registrerades, men notifieringen kunde inte skickas.',
            default => null,
        };
    }

    private function error(Request $request): ?string
    {
        return match ($request->query('error')) {
            'csrf' => 'Formuläret kunde inte verifieras. Försök igen.',
            'not_retryable' => 'Notifieringen kan inte skickas igen i sitt nuvarande läge.',
            'max_attempts' => 'Notifieringen har redan nått maximalt antal försök.',
            'transport' => 'Notifieringen kunde inte skickas igen. Kontrollera detaljerna och försök senare.',
            default => null,
        };
    }

    private function maskedEmail(string $email): string
    {
        if (!str_contains($email, '@')) {
            return 'Okänd mottagare';
        }

        [$localPart, $domain] = explode('@', $email, 2);
        $prefixLength = strlen($localPart) <= 2 ? 1 : 2;
        $prefix = substr($localPart, 0, $prefixLength);

        return $prefix . '***@' . $domain;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) || is_numeric($value) ? trim((string) $value) : '';
    }
}
