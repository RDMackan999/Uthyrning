<?php

$customer = is_array($customer ?? null) ? $customer : [];
$bookingHistory = is_array($bookingHistory ?? null) ? $bookingHistory : [];
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : [];
$csrfToken = is_string($csrfToken ?? null) ? $csrfToken : '';
$message = is_string($message ?? null) ? $message : null;
$error = is_string($error ?? null) ? $error : null;
$customerId = (string) ($customer['id'] ?? '');

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$statusLabel = static fn (mixed $value): string => $statusOptions[(string) $value] ?? (string) $value;
$typeLabel = static fn (mixed $value): string => match ((string) $value) {
    'company' => 'Företag',
    default => 'Privatperson',
};
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
            <h1><?= $escape($customer['name'] ?? 'Kund') ?></h1>
            <p><?= $escape($typeLabel($customer['customer_type_key'] ?? 'private')) ?> · <?= $escape($statusLabel($customer['status_key'] ?? '')) ?></p>
        </div>

        <div class="admin-inline-actions">
            <a class="admin-button admin-button-secondary" href="/admin/customers">Till kunder</a>
            <a class="admin-button" href="/admin/customers/<?= rawurlencode($customerId) ?>/edit">Redigera</a>
        </div>
    </div>

    <?php if ($message !== null): ?>
        <p class="admin-message" role="status"><?= $escape($message) ?></p>
    <?php endif; ?>

    <?php if ($error !== null): ?>
        <p class="admin-error" role="alert"><?= $escape($error) ?></p>
    <?php endif; ?>

    <h2>Översikt</h2>
    <div class="admin-readonly-grid">
        <div>
            <strong>Tekniskt id</strong>
            <span><?= $escape($customer['id'] ?? '') ?></span>
        </div>
        <div>
            <strong>Organisation</strong>
            <span><?= $escape($customer['organization_name'] ?? '-') ?></span>
        </div>
        <div>
            <strong>Status</strong>
            <span><?= $escape($statusLabel($customer['status_key'] ?? '')) ?></span>
        </div>
        <div>
            <strong>Kundtyp</strong>
            <span><?= $escape($typeLabel($customer['customer_type_key'] ?? 'private')) ?></span>
        </div>
        <div>
            <strong>E-post</strong>
            <span><?= $escape($customer['email'] ?? '-') ?></span>
        </div>
        <div>
            <strong>Telefon</strong>
            <span><?= $escape($customer['phone'] ?? '-') ?></span>
        </div>
        <div>
            <strong>Företag</strong>
            <span><?= $escape($customer['company_name'] ?? '-') ?></span>
        </div>
        <div>
            <strong>Skapad</strong>
            <span><?= $escape($customer['created_at'] ?? '') ?></span>
        </div>
    </div>

    <h2>Status</h2>
    <form class="admin-form" method="post" action="/admin/customers/<?= rawurlencode($customerId) ?>/status">
        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
        <div class="admin-form-grid">
            <label>
                Kundstatus
                <select name="status_key">
                    <?php foreach ($statusOptions as $value => $label): ?>
                        <option value="<?= $escape($value) ?>" <?= (string) ($customer['status_key'] ?? '') === (string) $value ? 'selected' : '' ?>>
                            <?= $escape($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="admin-form-actions">
            <button class="admin-button" type="submit">Uppdatera status</button>
        </div>
    </form>

    <h2>Bokningshistorik</h2>
    <?php if ($bookingHistory === []): ?>
        <p>Inga bokningar är kopplade till kunden ännu.</p>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Bokning</th>
                        <th>Objekt</th>
                        <th>Period</th>
                        <th>Status</th>
                        <th>Snapshot</th>
                        <th>Belopp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookingHistory as $booking): ?>
                        <?php if (!is_array($booking)) {
                            continue;
                        } ?>
                        <tr>
                            <td>
                                <a href="/admin/bookings/<?= rawurlencode((string) ($booking['public_id'] ?? '')) ?>">
                                    <?= $escape($booking['public_id'] ?? '') ?>
                                </a>
                            </td>
                            <td><?= $escape($booking['rental_item_names'] ?? '-') ?></td>
                            <td><?= $escape($booking['start_date'] ?? '') ?> - <?= $escape($booking['end_date'] ?? '') ?></td>
                            <td><?= $escape($booking['status_key'] ?? '') ?></td>
                            <td>
                                <?= $escape($booking['customer_name'] ?? '-') ?><br>
                                <?= $escape($booking['customer_email'] ?? '-') ?><br>
                                <?= $escape($booking['company_name'] ?? '-') ?>
                            </td>
                            <td><?= $money($booking['subtotal_amount'] ?? null, $booking['currency'] ?? 'SEK') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
