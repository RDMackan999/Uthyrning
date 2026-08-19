<?php

$notifications = is_array($notifications ?? null) ? $notifications : [];
$statusFilter = is_string($statusFilter ?? null) ? $statusFilter : '';
$eventFilter = is_string($eventFilter ?? null) ? $eventFilter : '';
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : [];
$eventOptions = is_array($eventOptions ?? null) ? $eventOptions : [];
$message = is_string($message ?? null) ? $message : null;
$error = is_string($error ?? null) ? $error : null;

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$statusLabel = static fn (string $key): string => (string) ($statusOptions[$key] ?? $key);
$eventLabel = static fn (string $key): string => (string) ($eventOptions[$key] ?? $key);
?>
<section class="admin-panel">
    <div class="admin-page-header">
        <div>
            <h1>Notifieringar</h1>
            <p>Operationell översikt över bokningsrelaterade e-postnotifieringar.</p>
        </div>
    </div>

    <?php if ($message !== null): ?>
        <p class="admin-message" role="status"><?= $escape($message) ?></p>
    <?php endif; ?>

    <?php if ($error !== null): ?>
        <p class="admin-error" role="alert"><?= $escape($error) ?></p>
    <?php endif; ?>

    <form class="admin-form" method="get" action="/admin/notifications">
        <div class="admin-form-grid">
            <label>
                Status
                <select name="status">
                    <option value="">Alla statusar</option>
                    <?php foreach ($statusOptions as $key => $label): ?>
                        <option value="<?= $escape($key) ?>" <?= $statusFilter === $key ? 'selected' : '' ?>>
                            <?= $escape($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                Händelse
                <select name="event">
                    <option value="">Alla händelser</option>
                    <?php foreach ($eventOptions as $key => $label): ?>
                        <option value="<?= $escape($key) ?>" <?= $eventFilter === $key ? 'selected' : '' ?>>
                            <?= $escape($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <div class="admin-form-actions">
            <button class="admin-button" type="submit">Filtrera</button>
            <a class="admin-button admin-button-secondary" href="/admin/notifications">Rensa</a>
        </div>
    </form>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Referens</th>
                    <th>Skapad</th>
                    <th>Händelse</th>
                    <th>Kanal</th>
                    <th>Mottagare</th>
                    <th>Status</th>
                    <th>Försök</th>
                    <th>Skickad</th>
                    <th>Organisation</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($notifications as $notification): ?>
                    <tr>
                        <td><?= $escape($notification['public_id'] ?? '') ?></td>
                        <td><?= $escape($notification['created_at'] ?? '') ?></td>
                        <td><?= $escape($eventLabel((string) ($notification['event_key'] ?? ''))) ?></td>
                        <td><?= $escape($notification['channel_key'] ?? '') ?></td>
                        <td><?= $escape($notification['recipient_display'] ?? 'Okänd mottagare') ?></td>
                        <td><?= $escape($statusLabel((string) ($notification['status_key'] ?? ''))) ?></td>
                        <td>
                            <?= $escape($notification['attempts_count'] ?? 0) ?>
                            /
                            <?= $escape($notification['max_attempts'] ?? 3) ?>
                        </td>
                        <td><?= $escape($notification['sent_at'] ?? '-') ?></td>
                        <td><?= $escape($notification['organization_name'] ?? '') ?></td>
                        <td>
                            <a href="/admin/notifications/<?= rawurlencode((string) ($notification['public_id'] ?? '')) ?>">Visa</a>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if ($notifications === []): ?>
                    <tr>
                        <td colspan="10">Inga notifieringar hittades.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
