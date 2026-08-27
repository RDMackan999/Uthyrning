<?php

$booking = is_array($booking ?? null) ? $booking : [];
$items = is_array($items ?? null) ? $items : [];
$data = is_array($data ?? null) ? $data : [];
$errors = is_array($errors ?? null) ? $errors : [];
$conditionOptions = is_array($conditionOptions ?? null) ? $conditionOptions : [];
$depositOptions = is_array($depositOptions ?? null) ? $depositOptions : [];
$csrfToken = is_string($csrfToken ?? null) ? $csrfToken : '';
$publicId = (string) ($booking['public_id'] ?? '');

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$money = static function (mixed $amount, mixed $currency) use ($escape): string {
    if ($amount === null || $amount === '') {
        return '-';
    }

    return $escape($amount) . ' ' . $escape($currency ?: 'SEK');
};
$itemValue = static function (int $bookingItemId, string $key, mixed $default = null) use ($data): mixed {
    $items = is_array($data['items'] ?? null) ? $data['items'] : [];
    $item = is_array($items[$bookingItemId] ?? null) ? $items[$bookingItemId] : [];

    return $item[$key] ?? $default;
};
?>
<section class="admin-panel">
    <div class="admin-page-header">
        <div>
            <h1>Lämna ut bokning <?= $escape($publicId) ?></h1>
            <p>Dokumentera faktisk utlämning och skick innan bokningen blir aktiv.</p>
        </div>

        <a class="admin-button admin-button-secondary" href="/admin/bookings/<?= rawurlencode($publicId) ?>">Till bokningen</a>
    </div>

    <?php if ($errors !== []): ?>
        <div class="admin-error" role="alert">
            <strong>Kontrollera formuläret.</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= $escape($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <h2>Bokning</h2>
    <div class="admin-readonly-grid">
        <div>
            <strong>Referens</strong>
            <span><?= $escape($publicId) ?></span>
        </div>
        <div>
            <strong>Kund</strong>
            <span><?= $escape($booking['customer_name'] ?? '-') ?></span>
        </div>
        <div>
            <strong>Organisation</strong>
            <span><?= $escape($booking['organization_name'] ?? '-') ?></span>
        </div>
        <div>
            <strong>Planerad period</strong>
            <span><?= $escape($booking['start_date'] ?? '') ?> - <?= $escape($booking['end_date'] ?? '') ?></span>
        </div>
        <div>
            <strong>Deposition enligt bokning</strong>
            <span><?= $money($booking['deposit_amount'] ?? null, $booking['currency'] ?? 'SEK') ?></span>
        </div>
        <div>
            <strong>Totalpris</strong>
            <span><?= $money($booking['subtotal_amount'] ?? null, $booking['currency'] ?? 'SEK') ?></span>
        </div>
    </div>

    <form class="admin-form" method="post" action="/admin/bookings/<?= rawurlencode($publicId) ?>/handover">
        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">

        <h2>Utlämning</h2>
        <div class="admin-form-grid">
            <label>
                Faktisk utlämningstid UTC
                <input type="text" name="actual_handover_at" value="<?= $escape($data['actual_handover_at'] ?? '') ?>" required>
            </label>
            <label>
                Mottaget av
                <input type="text" name="received_by_name" value="<?= $escape($data['received_by_name'] ?? '') ?>">
            </label>
            <label>
                Villkorsversion
                <input type="text" name="terms_version_key" value="<?= $escape($data['terms_version_key'] ?? '') ?>">
            </label>
        </div>

        <div class="admin-form-grid">
            <label>
                Depositionsstatus
                <select name="deposit_status_key">
                    <?php foreach ($depositOptions as $key => $label): ?>
                        <option value="<?= $escape($key) ?>" <?= ($data['deposit_status_key'] ?? '') === $key ? 'selected' : '' ?>>
                            <?= $escape($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Mottagen deposition
                <input type="number" min="0" step="0.01" name="deposit_received_amount" value="<?= $escape($data['deposit_received_amount'] ?? '') ?>">
            </label>
        </div>

        <label>
            Utlämningsnotering
            <textarea name="handover_note" rows="4"><?= $escape($data['handover_note'] ?? '') ?></textarea>
        </label>

        <h2>Objekt vid utlämning</h2>
        <?php if ($items === []): ?>
            <p>Inga objekt är kopplade till bokningen.</p>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Objekt</th>
                            <th>Planerad period</th>
                            <th>Skick</th>
                            <th>Notering</th>
                            <th>Mätarvärde</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php if (!is_array($item)) {
                                continue;
                            } ?>
                            <?php $bookingItemId = (int) ($item['id'] ?? 0); ?>
                            <tr>
                                <td><?= $escape($item['rental_item_name'] ?? '') ?></td>
                                <td><?= $escape($item['start_date'] ?? '') ?> - <?= $escape($item['end_date'] ?? '') ?></td>
                                <td>
                                    <select name="items[<?= $bookingItemId ?>][condition_key]" required>
                                        <?php foreach ($conditionOptions as $key => $label): ?>
                                            <option value="<?= $escape($key) ?>" <?= $itemValue($bookingItemId, 'condition_key', 'good') === $key ? 'selected' : '' ?>>
                                                <?= $escape($label) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" name="items[<?= $bookingItemId ?>][condition_note]" value="<?= $escape($itemValue($bookingItemId, 'condition_note')) ?>">
                                </td>
                                <td>
                                    <input type="number" min="0" step="0.01" name="items[<?= $bookingItemId ?>][meter_value]" value="<?= $escape($itemValue($bookingItemId, 'meter_value')) ?>">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="admin-form-actions">
            <button class="admin-button" type="submit">Registrera utlämning</button>
            <a class="admin-button admin-button-secondary" href="/admin/bookings/<?= rawurlencode($publicId) ?>">Avbryt</a>
        </div>
    </form>
</section>
