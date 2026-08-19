<?php

$notification = is_array($notification ?? null) ? $notification : [];
$attempts = is_array($attempts ?? null) ? $attempts : [];
$isRetryable = (bool) ($isRetryable ?? false);
$csrfToken = is_string($csrfToken ?? null) ? $csrfToken : '';
$message = is_string($message ?? null) ? $message : null;
$error = is_string($error ?? null) ? $error : null;

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$valueOrDash = static fn (mixed $value): string => trim((string) $value) === '' ? '-' : (string) $value;
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
            <span><?= $escape($notification['event_key'] ?? '') ?></span>
        </div>
        <div>
            <strong>Kanal</strong>
            <span><?= $escape($notification['channel_key'] ?? '') ?></span>
        </div>
        <div>
            <strong>Mottagartyp</strong>
            <span><?= $escape($notification['recipient_type'] ?? '') ?></span>
        </div>
        <div>
            <strong>Mottagare</strong>
            <span><?= $escape($notification['recipient_email'] ?? '') ?></span>
        </div>
        <div>
            <strong>Mall</strong>
            <span><?= $escape($notification['template_key'] ?? '') ?></span>
        </div>
        <div>
            <strong>Status</strong>
            <span><?= $escape($notification['status_key'] ?? '') ?></span>
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
            <span><?= $escape($valueOrDash($notification['booking_public_id'] ?? null)) ?></span>
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
                        <td><?= $escape($attempt['status_key'] ?? '') ?></td>
                        <td><?= $escape($attempt['transport_key'] ?? '') ?></td>
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
