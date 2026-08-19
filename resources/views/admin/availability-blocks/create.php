<?php

$item = is_array($item ?? null) ? $item : [];
$data = is_array($data ?? null) ? $data : [];
$errors = is_array($errors ?? null) ? $errors : [];
$reasonOptions = is_array($reasonOptions ?? null) ? $reasonOptions : [];
$csrfToken = is_string($csrfToken ?? null) ? $csrfToken : '';

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$publicId = (string) ($item['public_id'] ?? '');
$fieldError = static function (array $errors, string $field) use ($escape): string {
    return isset($errors[$field])
        ? '<em>' . $escape($errors[$field]) . '</em>'
        : '';
};
?>
<section class="admin-panel">
    <div class="admin-page-header">
        <div>
            <h1>Ny kalenderblockering</h1>
            <p>Blockera datum f&ouml;r <?= $escape($item['name'] ?? 'objektet') ?>.</p>
        </div>

        <a class="admin-button admin-button-secondary" href="/admin/items/<?= rawurlencode($publicId) ?>/availability">Till blockeringar</a>
    </div>

    <?php if (isset($errors['form'])): ?>
        <p class="admin-error"><?= $escape($errors['form']) ?></p>
    <?php endif; ?>

    <form class="admin-form" method="post" action="/admin/items/<?= rawurlencode($publicId) ?>/availability" novalidate>
        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">

        <div class="admin-form-grid">
            <label>
                Startdatum
                <input name="start_date" type="date" value="<?= $escape($data['start_date'] ?? '') ?>" required>
                <?= $fieldError($errors, 'start_date') ?>
            </label>

            <label>
                Slutdatum
                <input name="end_date" type="date" value="<?= $escape($data['end_date'] ?? '') ?>" required>
                <?= $fieldError($errors, 'end_date') ?>
            </label>

            <label>
                Blockeringstyp
                <select name="reason_code" required>
                    <?php foreach ($reasonOptions as $value => $label): ?>
                        <option value="<?= $escape($value) ?>" <?= ($data['reason_code'] ?? 'manual') === $value ? 'selected' : '' ?>>
                            <?= $escape($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= $fieldError($errors, 'reason_code') ?>
            </label>
        </div>

        <label>
            Intern notering
            <textarea name="internal_note" maxlength="1000"><?= $escape($data['internal_note'] ?? '') ?></textarea>
        </label>

        <div class="admin-form-actions">
            <button class="admin-button" type="submit">Skapa blockering</button>
            <a class="admin-button admin-button-secondary" href="/admin/items/<?= rawurlencode($publicId) ?>/availability">Avbryt</a>
        </div>
    </form>
</section>
