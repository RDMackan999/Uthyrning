<?php

use App\Helpers\StatusLabels;

$notification = is_array($notification ?? null) ? $notification : [];
$attempts = is_array($attempts ?? null) ? $attempts : [];
$isRetryable = (bool) ($isRetryable ?? false);
$csrfToken = is_string($csrfToken ?? null) ? $csrfToken : '';
$message = is_string($message ?? null) ? $message : null;
$error = is_string($error ?? null) ? $error : null;

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$valueOrDash = static fn (mixed $value): string => trim((string) $value) === '' ? '-' : (string) $value;
$statusLabel = static fn (mixed $value): string => StatusLabels::notification($value);
$eventLabel = static fn (mixed $value): string => StatusLabels::notificationEvent($value);
$channelLabel = static fn (mixed $value): string => match ((string) $value) {
    'email' => 'E-post',
    default => $valueOrDash($value),
};
$recipientTypeLabel = static fn (mixed $value): string => match ((string) $value) {
    'customer' => 'Kund',
    'admin' => 'Administratör',
    default => $valueOrDash($value),
};
$templateLabel = static fn (mixed $value): string => match ((string) $value) {
    'booking_created_customer' => 'Bekräftelse till kund',
    'booking_created_admin' => 'Ny förfrågan till admin',
    'booking_approved_customer' => 'Godkännande till kund',
    'booking_rejected_customer' => 'Avslag till kund',
    'booking_cancelled_customer' => 'Avbruten bokning till kund',
    default => $valueOrDash($value),
};
$transportLabel = static fn (mixed $value): string => match ((string) $value) {
    'development' => 'Testtransport',
    'smtp' => 'SMTP',
    default => $valueOrDash($value),
};
$bookingPublicId = trim((string) ($notification['booking_public_id'] ?? ''));
?>
<section class="admin-panel">
    <div class="admin-page-header">
        <div>
            <h1>Notifiering</h1>
            <p><?= $escape($notification['public_id'] ?? '') ?></p>
        </div>
        <a class="admin-button admin-button-secondary" href="/admin/notifications">Till notifieringar</a>
    </div>

    <?php if ($message !== null): ?>
        <p class="admin-message" role="status"><?= $escape($message) ?></p>
    <?php endif; ?>

    <?php if ($error !== null): ?>
        <p class="admin-error" role="alert"><?= $escape($error) ?></p>
    <?php endif; ?>

    <div class="admin-readonly-grid">
        <div>
            <strong>Händelse</strong>
            <span><?= $escape($eventLabel($notification['event_key'] ?? '')) ?></span>
        </div>
        <div>
            <strong>Kanal</strong>
            <span><?= $escape($channelLabel($notification['channel_key'] ?? '')) ?></span>
        </div>
        <div>
            <strong>Mottagartyp</strong>
            <span><?= $escape($recipientTypeLabel($notification['recipient_type'] ?? '')) ?></span>
        </div>
        <div>
            <strong>Mottagare</strong>
            <span><?= $escape($notification['recipient_email'] ?? '') ?></span>
        </div>
        <div>
            <strong>Mall</strong>
            <span><?= $escape($templateLabel($notification['template_key'] ?? '')) ?></span>
        </div>
        <div>
            <strong>Status</strong>
            <span><?= $escape($statusLabel($notification['status_key'] ?? '')) ?></span>
        </div>
        <div>
            <strong>Försök</strong>
            <span>
                <?= $escape($notification['attempts_count'] ?? 0) ?>
                /
                <?= $escape($notification['max_attempts'] ?? 3) ?>
            </span>
        </div>
        <div>
            <strong>Skapad</strong>
            <span><?= $escape($notification['created_at'] ?? '') ?></span>
        </div>
        <div>
            <strong>Skickad</strong>
            <span><?= $escape($valueOrDash($notification['sent_at'] ?? null)) ?></span>
        </div>
        <div>
            <strong>Organisation</strong>
            <span><?= $escape($notification['organization_name'] ?? '') ?></span>
        </div>
        <div>
            <strong>Bokningsreferens</strong>
            <span>
                <?php if ($bookingPublicId !== ''): ?>
                    <a href="/admin/bookings/<?= rawurlencode($bookingPublicId) ?>"><?= $escape($bookingPublicId) ?></a>
                <?php else: ?>
                    -
                <?php endif; ?>
            </span>
        </div>
        <div>
            <strong>Senaste felkategori</strong>
            <span><?= $escape($valueOrDash($notification['last_error_code'] ?? null)) ?></span>
        </div>
    </div>

    <div class="admin-publication-actions">
        <?php if ($isRetryable): ?>
            <form method="post" action="/admin/notifications/<?= rawurlencode((string) ($notification['public_id'] ?? '')) ?>/retry">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <button class="admin-button" type="submit">Försök igen</button>
            </form>
        <?php else: ?>
            <button class="admin-button" type="button" disabled>Försök igen</button>
        <?php endif; ?>
    </div>
</section>

<section class="admin-panel">
    <div class="admin-page-header">
        <div>
            <h2>Försökshistorik</h2>
            <p>Append-only historik över leveransförsök.</p>
        </div>
    </div>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Försök</th>
                    <th>Status</th>
                    <th>Transport</th>
                    <th>Säker felkategori</th>
                    <th>Försökt</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($attempts as $attempt): ?>
                    <tr>
                        <td><?= $escape($attempt['attempt_number'] ?? '') ?></td>
                        <td><?= $escape($statusLabel($attempt['status_key'] ?? '')) ?></td>
                        <td><?= $escape($transportLabel($attempt['transport_key'] ?? '')) ?></td>
                        <td><?= $escape($valueOrDash($attempt['error_code'] ?? null)) ?></td>
                        <td><?= $escape($attempt['attempted_at'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if ($attempts === []): ?>
                    <tr>
                        <td colspan="5">Inga försök har registrerats ännu.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
