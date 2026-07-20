<?php

$data = is_array($data ?? null) ? $data : [];
$errors = is_array($errors ?? null) ? $errors : [];
$organizations = is_array($organizations ?? null) ? $organizations : [];
$categories = is_array($categories ?? null) ? $categories : [];
$csrfToken = is_string($csrfToken ?? null) ? $csrfToken : '';
$message = is_string($message ?? null) ? $message : null;
$formAction = is_string($formAction ?? null) ? $formAction : '/admin/items';
$formTitle = is_string($formTitle ?? null) ? $formTitle : 'Objekt';
$submitLabel = is_string($submitLabel ?? null) ? $submitLabel : 'Spara';
$item = is_array($item ?? null) ? $item : null;
$publicationStatus = $item !== null && is_scalar($item['publication_status_key'] ?? null)
    ? (string) $item['publication_status_key']
    : 'draft';

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
            <p>Grunduppgifter för uthyrningsobjekt.</p>
        </div>

        <a class="admin-button admin-button-secondary" href="/admin/items">Till objektlistan</a>
    </div>

    <?php if ($message !== null): ?>
        <p class="admin-message" role="status"><?= $escape($message) ?></p>
    <?php endif; ?>

    <?php if ($errorFor('form') !== null): ?>
        <p class="admin-error" role="alert"><?= $escape($errorFor('form')) ?></p>
    <?php endif; ?>

    <form class="admin-form" method="post" action="<?= $escape($formAction) ?>">
        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">

        <?php if ($item !== null): ?>
            <div class="admin-readonly-grid">
                <div>
                    <span>Tekniskt ID</span>
                    <strong><?= $escape($item['id'] ?? '') ?></strong>
                </div>
                <div>
                    <span>Public ID</span>
                    <strong><?= $escape($item['public_id'] ?? '') ?></strong>
                </div>
            </div>
        <?php else: ?>
            <div class="admin-readonly-grid">
                <div>
                    <span>Tekniskt ID</span>
                    <strong>Skapas vid sparning</strong>
                </div>
                <div>
                    <span>Public ID</span>
                    <strong>Skapas vid sparning</strong>
                </div>
            </div>
        <?php endif; ?>

        <div class="admin-form-grid">
            <label>
                <span>Namn</span>
                <input name="name" type="text" value="<?= $escape($value('name')) ?>" required>
                <?php if ($errorFor('name') !== null): ?>
                    <em><?= $escape($errorFor('name')) ?></em>
                <?php endif; ?>
            </label>

            <label>
                <span>Kortnamn</span>
                <input name="short_name" type="text" value="<?= $escape($value('short_name')) ?>">
            </label>

            <label>
                <span>Slug</span>
                <input name="slug" type="text" value="<?= $escape($value('slug')) ?>" required>
                <?php if ($errorFor('slug') !== null): ?>
                    <em><?= $escape($errorFor('slug')) ?></em>
                <?php endif; ?>
            </label>

            <label>
                <span>Organisation</span>
                <select name="organization_id" required>
                    <option value="">Välj organisation</option>
                    <?php foreach ($organizations as $organization): ?>
                        <?php if (!is_array($organization)) {
                            continue;
                        } ?>
                        <option value="<?= $escape($organization['id'] ?? '') ?>"<?= $isSelected($value('organization_id'), $organization['id'] ?? '') ?>>
                            <?= $escape($organization['name'] ?? '') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($errorFor('organization_id') !== null): ?>
                    <em><?= $escape($errorFor('organization_id')) ?></em>
                <?php endif; ?>
            </label>

            <label>
                <span>Primär kategori</span>
                <select name="primary_category_id" required>
                    <option value="">Välj kategori</option>
                    <?php foreach ($categories as $category): ?>
                        <?php if (!is_array($category)) {
                            continue;
                        } ?>
                        <option value="<?= $escape($category['id'] ?? '') ?>"<?= $isSelected($value('primary_category_id'), $category['id'] ?? '') ?>>
                            <?= $escape($category['name'] ?? '') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($errorFor('primary_category_id') !== null): ?>
                    <em><?= $escape($errorFor('primary_category_id')) ?></em>
                <?php endif; ?>
            </label>
        </div>

        <label>
            <span>Beskrivning</span>
            <textarea name="description" rows="6"><?= $escape($value('description')) ?></textarea>
        </label>

        <div class="admin-checkboxes">
            <label>
                <input name="is_active" type="checkbox" value="1"<?= $isChecked('is_active') ?>>
                <span>Aktiv</span>
            </label>

            <label>
                <input name="is_rentable" type="checkbox" value="1"<?= $isChecked('is_rentable') ?>>
                <span>Uthyrningsbar</span>
            </label>
        </div>

        <div class="admin-actions">
            <button class="admin-button" type="submit"><?= $escape($submitLabel) ?></button>
            <?php if ($item !== null): ?>
                <a class="admin-button admin-button-secondary" href="/admin/items/<?= rawurlencode((string) ($item['public_id'] ?? '')) ?>/rates">Hantera priser</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($item !== null): ?>
        <div class="admin-publication-actions">
            <form method="post" action="<?= $escape($formAction) ?>">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="_action" value="publish">
                <button class="admin-button" type="submit"<?= $publicationStatus === 'published' ? ' disabled' : '' ?>>Publicera</button>
            </form>

            <form method="post" action="<?= $escape($formAction) ?>">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="_action" value="unpublish">
                <button class="admin-button admin-button-secondary" type="submit"<?= $publicationStatus !== 'published' ? ' disabled' : '' ?>>Avpublicera</button>
            </form>

            <form method="post" action="<?= $escape($formAction) ?>">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="_action" value="archive">
                <button class="admin-button admin-button-danger" type="submit">Arkivera</button>
            </form>
        </div>

    <?php endif; ?>
</section>
