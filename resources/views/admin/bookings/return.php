<?php

$booking = is_array($booking ?? null) ? $booking : [];
$fulfillment = is_array($fulfillment ?? null) ? $fulfillment : [];
$fulfillmentItems = is_array($fulfillmentItems ?? null) ? $fulfillmentItems : [];
$data = is_array($data ?? null) ? $data : [];
$errors = is_array($errors ?? null) ? $errors : [];
$conditionOptions = is_array($conditionOptions ?? null) ? $conditionOptions : [];
$depositOptions = is_array($depositOptions ?? null) ? $depositOptions : [];
$csrfToken = is_string($csrfToken ?? null) ? $csrfToken : '';
$isLateReturn = (bool) ($isLateReturn ?? false);
$publicId = (string) ($booking['public_id'] ?? '');

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$money = static function (mixed $amount, mixed $currency) use ($escape): string {
    if ($amount === null || $amount === '') {
        return '-';
    }

    return $escape($amount) . ' ' . $escape($currency ?: 'SEK');
};
$conditionLabel = static fn (mixed $value): string => (string) ($conditionOptions[(string) $value] ?? $value ?? '-');
$depositLabel = static fn (mixed $value): string => (string) ($depositOptions[(string) $value] ?? $value ?? '-');
$itemValue = static function (int $bookingItemId, string $key, mixed $default = null) use ($data): mixed {
    $items = is_array($data['items'] ?? null) ? $data['items'] : [];
    $item = is_array($items[$bookingItemId] ?? null) ? $items[$bookingItemId] : [];

    return $item[$key] ?? $default;
};
?>
<section class="admin-panel">
    <div class="admin-page-header">
        <div>
            <h1>Registrera återlämning <?= $escape($publicId) ?></h1>
            <p>Dokumentera faktisk återlämning och skick innan bokningen slutförs.</p>
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
            <strong>Faktisk utlämning</strong>
            <span><?= $escape($fulfillment['actual_handover_at'] ?? '-') ?></span>
        </div>
        <div>
            <strong>Depositionsstatus</strong>
            <span><?= $escape($depositLabel($fulfillment['deposit_status_key'] ?? '-')) ?></span>
        </div>
    </div>

    <h2>Utlämnat skick</h2>
    <?php if ($fulfillmentItems === []): ?>
        <p>Inga utlämnade objekt finns.</p>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Objekt</th>
                        <th>Planerad period</th>
                        <th>Utlämnat skick</th>
                        <th>Utlämningsnotering</th>
                        <th>Mätarvärde</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fulfillmentItems as $item): ?>
                        <?php if (!is_array($item)) {
                            continue;
                        } ?>
                        <tr>
                            <td><?= $escape($item['item_name_snapshot'] ?? '') ?></td>
                            <td><?= $escape($item['start_date'] ?? '') ?> - <?= $escape($item['end_date'] ?? '') ?></td>
                            <td><?= $escape($conditionLabel($item['handover_condition_key'] ?? '-')) ?></td>
                            <td><?= $escape($item['handover_condition_note'] ?? '-') ?></td>
                            <td><?= $escape($item['meter_value_handover'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <form class="admin-form" method="post" action="/admin/bookings/<?= rawurlencode($publicId) ?>/return">
        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">

        <h2>Återlämning</h2>
        <div class="admin-form-grid">
            <label>
                Faktisk återlämningstid UTC
                <input type="text" name="actual_return_at" value="<?= $escape($data['actual_return_at'] ?? '') ?>" required>
            </label>
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
        </div>

        <div class="admin-form-grid">
            <label>
                Återbetald deposition
                <input type="number" min="0" step="0.01" name="deposit_returned_amount" value="<?= $escape($data['deposit_returned_amount'] ?? '') ?>">
            </label>
            <label>
                Innehållen deposition
                <input type="number" min="0" step="0.01" name="deposit_retained_amount" value="<?= $escape($data['deposit_retained_amount'] ?? '') ?>">
            </label>
        </div>

        <?php if ($isLateReturn): ?>
            <p class="admin-error" role="status">Sen återlämning</p>
        <?php endif; ?>

        <label>
            Återlämningsnotering
            <textarea name="return_note" rows="4"><?= $escape($data['return_note'] ?? '') ?></textarea>
        </label>

        <h2>Objekt vid återlämning</h2>
        <?php if ($fulfillmentItems === []): ?>
            <p>Inga utlämnade objekt finns.</p>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Objekt</th>
                            <th>Skick</th>
                            <th>Notering</th>
                            <th>Avvikelse</th>
                            <th>Skadenotering</th>
                            <th>Mätarvärde</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fulfillmentItems as $item): ?>
                            <?php if (!is_array($item)) {
                                continue;
                            } ?>
                            <?php $bookingItemId = (int) ($item['booking_item_id'] ?? 0); ?>
                            <tr>
                                <td><?= $escape($item['item_name_snapshot'] ?? '') ?></td>
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
                                    <label>
                                        <input type="checkbox" name="items[<?= $bookingItemId ?>][has_return_deviation]" value="1" <?= $itemValue($bookingItemId, 'has_return_deviation') ? 'checked' : '' ?>>
                                        Ja
                                    </label>
                                </td>
                                <td>
                                    <input type="text" name="items[<?= $bookingItemId ?>][damage_note]" value="<?= $escape($itemValue($bookingItemId, 'damage_note')) ?>">
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
            <button class="admin-button" type="submit">Registrera återlämning</button>
            <a class="admin-button admin-button-secondary" href="/admin/bookings/<?= rawurlencode($publicId) ?>">Avbryt</a>
        </div>
    </form>
</section>
