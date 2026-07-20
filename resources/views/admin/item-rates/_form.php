<?php

$item = is_array($item ?? null) ? $item : [];
$rate = is_array($rate ?? null) ? $rate : null;
$data = is_array($data ?? null) ? $data : [];
$errors = is_array($errors ?? null) ? $errors : [];
$rateTypes = is_array($rateTypes ?? null) ? $rateTypes : [];
$csrfToken = is_string($csrfToken ?? null) ? $csrfToken : '';
$formAction = is_string($formAction ?? null) ? $formAction : '';
$formTitle = is_string($formTitle ?? null) ? $formTitle : 'Pris';
$submitLabel = is_string($submitLabel ?? null) ? $submitLabel : 'Spara';
$publicId = (string) ($item['public_id'] ?? '');

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$value = static fn (string $key): string => is_scalar($data[$key] ?? null) ? (string) $data[$key] : '';
$isChecked = static fn (string $key): string => filter_var($data[$key] ?? false, FILTER_VALIDATE_BOOLEAN) ? ' checked' : '';
$isSelected = static fn (mixed $actual, mixed $expected): string => (string) $actual === (string) $expected ? ' selected' : '';
$errorFor = static fn (string $key): ?string => is_string($errors[$key] ?? null) ? (string) $errors[$key] : null;
?>
<section class="admin-panel">
    <div class="admin-page-header">
        <div>
            <h1><?= $escape($formTitle) ?></h1>
            <p><?= $escape($item['name'] ?? '') ?></p>
        </div>

        <a class="admin-button admin-button-secondary" href="/admin/items/<?= rawurlencode($publicId) ?>/rates">Till prislistan</a>
    </div>

    <?php if ($errorFor('form') !== null): ?>
        <p class="admin-error" role="alert"><?= $escape($errorFor('form')) ?></p>
    <?php endif; ?>

    <form class="admin-form" method="post" action="<?= $escape($formAction) ?>">
        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">

        <?php if ($rate !== null): ?>
            <div class="admin-readonly-grid">
                <div>
                    <span>Tekniskt pris-ID</span>
                    <strong><?= $escape($rate['id'] ?? '') ?></strong>
                </div>
                <div>
                    <span>Objektets Public ID</span>
                    <strong><?= $escape($publicId) ?></strong>
                </div>
            </div>
        <?php endif; ?>

        <div class="admin-form-grid">
            <label>
                <span>Pristyp</span>
                <select name="rate_type" required>
                    <?php foreach ($rateTypes as $type => $label): ?>
                        <option value="<?= $escape($type) ?>"<?= $isSelected($value('rate_type'), $type) ?>>
                            <?= $escape($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($errorFor('rate_type') !== null): ?>
                    <em><?= $escape($errorFor('rate_type')) ?></em>
                <?php endif; ?>
            </label>

            <label>
                <span>Belopp</span>
                <input name="amount" type="number" min="0.01" step="0.01" value="<?= $escape($value('amount')) ?>" required>
                <?php if ($errorFor('amount') !== null): ?>
                    <em><?= $escape($errorFor('amount')) ?></em>
                <?php endif; ?>
            </label>

            <label>
                <span>Valuta</span>
                <select name="currency" required>
                    <option value="SEK"<?= $isSelected($value('currency'), 'SEK') ?>>SEK</option>
                </select>
                <?php if ($errorFor('currency') !== null): ?>
                    <em><?= $escape($errorFor('currency')) ?></em>
                <?php endif; ?>
            </label>
        </div>

        <div class="admin-checkboxes">
            <label>
                <input name="is_active" type="checkbox" value="1"<?= $isChecked('is_active') ?>>
                <span>Aktiv</span>
            </label>
        </div>

        <div class="admin-actions">
            <button class="admin-button" type="submit"><?= $escape($submitLabel) ?></button>
        </div>
    </form>
</section>
