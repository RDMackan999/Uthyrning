<?php

$bookings = is_array($bookings ?? null) ? $bookings : [];
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : [];
$statusFilter = is_string($statusFilter ?? null) ? $statusFilter : '';
$message = is_string($message ?? null) ? $message : null;

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$statusLabel = static fn (mixed $value): string => $statusOptions[(string) $value] ?? (string) $value;
$money = static function (mixed $amount, mixed $currency) use ($escape): string {
    if ($amount === null || $amount === '') {
        return '-';
    }

    return $escape($amount) . ' ' . $escape($currency ?: 'SEK');
};
?>
<section class="admin-panel">
    <div class="admin-page-header">
        <div>
            <h1>Bokningar</h1>
            <p>Granska och hantera inkomna bokningsförfrågningar.</p>
        </div>
    </div>

    <?php if ($message !== null): ?>
        <p class="admin-message" role="status"><?= $escape($message) ?></p>
    <?php endif; ?>

    <form class="admin-form" method="get" action="/admin/bookings">
        <label>
            Status
            <select name="status">
                <option value="">Alla statusar</option>
                <?php foreach ($statusOptions as $value => $label): ?>
                    <option value="<?= $escape($value) ?>" <?= $statusFilter === (string) $value ? 'selected' : '' ?>>
                        <?= $escape($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <div>
            <button class="admin-button" type="submit">Filtrera</button>
            <a class="admin-button admin-button-secondary" href="/admin/bookings">Rensa</a>
        </div>
    </form>

    <?php if ($bookings === []): ?>
        <p>Inga bokningar matchar urvalet.</p>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Referens</th>
                        <th>Skapad</th>
                        <th>Status</th>
                        <th>Kund</th>
                        <th>Objekt</th>
                        <th>Period</th>
                        <th>Totalpris</th>
                        <th>Åtgärd</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking): ?>
                        <?php if (!is_array($booking)) {
                            continue;
                        } ?>
                        <tr>
                            <td><code><?= $escape($booking['public_id'] ?? '') ?></code></td>
                            <td><?= $escape($booking['created_at'] ?? '') ?></td>
                            <td><?= $escape($statusLabel($booking['status_key'] ?? '')) ?></td>
                            <td><?= $escape($booking['customer_name'] ?? '-') ?></td>
                            <td><?= $escape($booking['rental_item_names'] ?? '-') ?></td>
                            <td><?= $escape($booking['start_date'] ?? '') ?> - <?= $escape($booking['end_date'] ?? '') ?></td>
                            <td><?= $money($booking['subtotal_amount'] ?? null, $booking['currency'] ?? 'SEK') ?></td>
                            <td>
                                <a href="/admin/bookings/<?= rawurlencode((string) ($booking['public_id'] ?? '')) ?>">Visa</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
