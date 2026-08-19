<?php

$booking = is_array($booking ?? null) ? $booking : [];
$items = is_array($items ?? null) ? $items : [];
$statusHistory = is_array($statusHistory ?? null) ? $statusHistory : [];
$internalNotes = is_array($internalNotes ?? null) ? $internalNotes : [];
$availableActions = is_array($availableActions ?? null) ? $availableActions : [];
$csrfToken = is_string($csrfToken ?? null) ? $csrfToken : '';
$message = is_string($message ?? null) ? $message : null;
$error = is_string($error ?? null) ? $error : null;
$publicId = (string) ($booking['public_id'] ?? '');

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$statusLabels = [
    'request' => 'Förfrågan',
    'approved' => 'Godkänd',
    'rejected' => 'Nekad',
    'cancelled' => 'Avbokad',
    'active' => 'Aktiv',
    'completed' => 'Slutförd',
];
$statusLabel = static fn (mixed $value): string => $statusLabels[(string) $value] ?? (string) $value;
$money = static function (mixed $amount, mixed $currency) use ($escape): string {
    if ($amount === null || $amount === '') {
        return '-';
    }

    return $escape($amount) . ' ' . $escape($currency ?: 'SEK');
};
$actionPath = static fn (string $status): string => match ($status) {
    'approved' => 'approve',
    'rejected' => 'reject',
    'cancelled' => 'cancel',
    'active' => 'start',
    'completed' => 'complete',
    default => '',
};
?>
<section class="admin-panel">
    <div class="admin-page-header">
        <div>
            <h1>Bokning <?= $escape($publicId) ?></h1>
            <p><?= $escape($statusLabel($booking['status_key'] ?? '')) ?></p>
        </div>

        <a class="admin-button admin-button-secondary" href="/admin/bookings">Till bokningar</a>
    </div>

    <?php if ($message !== null): ?>
        <p class="admin-message" role="status"><?= $escape($message) ?></p>
    <?php endif; ?>

    <?php if ($error !== null): ?>
        <p class="admin-error" role="alert"><?= $escape($error) ?></p>
    <?php endif; ?>

    <?php if ($availableActions !== []): ?>
        <div class="admin-publication-actions" aria-label="Bokningsåtgärder">
            <?php foreach ($availableActions as $targetStatus => $label): ?>
                <?php $path = $actionPath((string) $targetStatus); ?>
                <?php if ($path === '') {
                    continue;
                } ?>
                <form method="post" action="/admin/bookings/<?= rawurlencode($publicId) ?>/<?= $escape($path) ?>">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <?php if ($targetStatus === 'cancelled'): ?>
                        <input type="hidden" name="status_comment" value="Administrativ avbokning via admin.">
                    <?php endif; ?>
                    <button class="admin-button <?= $targetStatus === 'rejected' || $targetStatus === 'cancelled' ? 'admin-button-danger' : '' ?>" type="submit">
                        <?= $escape($label) ?>
                    </button>
                </form>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <h2>Översikt</h2>
    <div class="admin-readonly-grid">
        <div>
            <strong>Referens</strong>
            <span><?= $escape($publicId) ?></span>
        </div>
        <div>
            <strong>Status</strong>
            <span><?= $escape($statusLabel($booking['status_key'] ?? '')) ?></span>
        </div>
        <div>
            <strong>Skapad</strong>
            <span><?= $escape($booking['created_at'] ?? '') ?></span>
        </div>
        <div>
            <strong>Period</strong>
            <span><?= $escape($booking['start_date'] ?? '') ?> - <?= $escape($booking['end_date'] ?? '') ?></span>
        </div>
        <div>
            <strong>Organisation</strong>
            <span><?= $escape($booking['organization_name'] ?? '') ?></span>
        </div>
        <div>
            <strong>Totalpris</strong>
            <span><?= $money($booking['subtotal_amount'] ?? null, $booking['currency'] ?? 'SEK') ?></span>
        </div>
        <div>
            <strong>Deposition</strong>
            <span><?= $money($booking['deposit_amount'] ?? null, $booking['currency'] ?? 'SEK') ?></span>
        </div>
        <div>
            <strong>Antal dagar</strong>
            <span><?= $escape($booking['total_units'] ?? '') ?></span>
        </div>
    </div>

    <h2>Kundsnapshot</h2>
    <div class="admin-readonly-grid">
        <div>
            <strong>Namn</strong>
            <span><?= $escape($booking['customer_name'] ?? '-') ?></span>
        </div>
        <div>
            <strong>E-post</strong>
            <span><?= $escape($booking['customer_email'] ?? '-') ?></span>
        </div>
        <div>
            <strong>Telefon</strong>
            <span><?= $escape($booking['customer_phone'] ?? '-') ?></span>
        </div>
        <div>
            <strong>Företag</strong>
            <span><?= $escape($booking['company_name'] ?? '-') ?></span>
        </div>
    </div>

    <h2>Kommentarer</h2>
    <div class="admin-readonly-grid">
        <div>
            <strong>Kundkommentar</strong>
            <span><?= $escape($booking['customer_comment'] ?? '-') ?></span>
        </div>
        <div>
            <strong>Intern notering</strong>
            <span><?= $escape($booking['internal_note'] ?? '-') ?></span>
        </div>
    </div>

    <h2>Objekt och pris-snapshot</h2>
    <?php if ($items === []): ?>
        <p>Inga objekt är kopplade till bokningen.</p>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Objekt</th>
                        <th>Period</th>
                        <th>Pris</th>
                        <th>Antal</th>
                        <th>Delsumma</th>
                        <th>Deposition</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <?php if (!is_array($item)) {
                            continue;
                        } ?>
                        <tr>
                            <td><?= $escape($item['rental_item_name'] ?? '') ?></td>
                            <td><?= $escape($item['start_date'] ?? '') ?> - <?= $escape($item['end_date'] ?? '') ?></td>
                            <td><?= $money($item['snapshot_unit_price'] ?? $item['unit_price'] ?? null, $item['snapshot_currency'] ?? $item['currency'] ?? 'SEK') ?></td>
                            <td><?= $escape($item['snapshot_number_of_units'] ?? $item['number_of_units'] ?? '') ?></td>
                            <td><?= $money($item['snapshot_subtotal_amount'] ?? $item['subtotal_amount'] ?? null, $item['snapshot_currency'] ?? $item['currency'] ?? 'SEK') ?></td>
                            <td><?= $money($item['snapshot_deposit_amount'] ?? $item['deposit_amount'] ?? null, $item['snapshot_currency'] ?? $item['currency'] ?? 'SEK') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <h2>Interna noteringar</h2>
    <?php if ($internalNotes === []): ?>
        <p>Inga interna noteringar finns.</p>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Typ</th>
                        <th>Notering</th>
                        <th>Skapad</th>
                        <th>Skapad av</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($internalNotes as $note): ?>
                        <?php if (!is_array($note)) {
                            continue;
                        } ?>
                        <tr>
                            <td><?= $escape($note['note_type'] ?? '') ?></td>
                            <td><?= $escape($note['body'] ?? '') ?></td>
                            <td><?= $escape($note['created_at'] ?? '') ?></td>
                            <td><?= $escape($note['created_by_email'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <h2>Statushistorik</h2>
    <?php if ($statusHistory === []): ?>
        <p>Ingen statushistorik finns.</p>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Tid</th>
                        <th>Från</th>
                        <th>Till</th>
                        <th>Ändrad av</th>
                        <th>Kommentar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($statusHistory as $history): ?>
                        <?php if (!is_array($history)) {
                            continue;
                        } ?>
                        <tr>
                            <td><?= $escape($history['created_at'] ?? '') ?></td>
                            <td><?= $escape($statusLabel($history['from_status_key'] ?? '-')) ?></td>
                            <td><?= $escape($statusLabel($history['to_status_key'] ?? '')) ?></td>
                            <td><?= $escape($history['changed_by_email'] ?? '-') ?></td>
                            <td><?= $escape($history['comment'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
