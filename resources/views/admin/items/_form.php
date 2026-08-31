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
$mediaItems = is_array($mediaItems ?? null) ? $mediaItems : [];
$publicationStatus = $item !== null && is_scalar($item['publication_status_key'] ?? null)
    ? (string) $item['publication_status_key']
    : 'draft';

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$value = static fn (string $key): string => is_scalar($data[$key] ?? null) ? (string) $data[$key] : '';
$isChecked = static fn (string $key): string => filter_var($data[$key] ?? false, FILTER_VALIDATE_BOOLEAN) ? ' checked' : '';
$isSelected = static fn (mixed $actual, mixed $expected): string => (string) $actual === (string) $expected ? ' selected' : '';
$errorFor = static fn (string $key): ?string => is_string($errors[$key] ?? null) ? (string) $errors[$key] : null;
$formatBytes = static function (mixed $bytes): string {
    if (!is_numeric($bytes)) {
        return '';
    }

    $size = (float) $bytes;
    if ($size >= 1048576) {
        return number_format($size / 1048576, 1, ',', ' ') . ' MB';
    }

    return number_format($size / 1024, 0, ',', ' ') . ' kB';
};
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
                    <span>Objektreferens</span>
                    <strong><?= $escape($item['public_id'] ?? '') ?></strong>
                </div>
            </div>
        <?php else: ?>
            <div class="admin-readonly-grid">
                <div>
                    <span>Objektreferens</span>
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
                <a class="admin-button admin-button-secondary" href="/admin/items/<?= rawurlencode((string) ($item['public_id'] ?? '')) ?>/availability">Kalenderblockeringar</a>
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

    <?php if ($item !== null): ?>
        <?php
        $itemPublicId = (string) ($item['public_id'] ?? '');
        $mediaUploadAction = '/admin/items/' . rawurlencode($itemPublicId) . '/media';
        $mediaSortAction = '/admin/items/' . rawurlencode($itemPublicId) . '/media/sort';
        ?>
        <section class="admin-media-section" aria-labelledby="item-media-heading">
            <div class="admin-page-header">
                <div>
                    <h2 id="item-media-heading">Bilder</h2>
                    <p>Ladda upp JPEG, PNG eller WebP, max 8 MB per bild. Filer lagras privat och visas via säkra routes.</p>
                </div>
            </div>

            <form class="admin-media-upload" method="post" action="<?= $escape($mediaUploadAction) ?>" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <label>
                    <span>Välj bilder</span>
                    <input name="images[]" type="file" accept="image/jpeg,image/png,image/webp" multiple required>
                </label>
                <button class="admin-button" type="submit">Ladda upp</button>
                <p class="admin-media-hint">Du kan välja flera bilder. Uppladdningen fungerar utan JavaScript.</p>
            </form>

            <?php if ($mediaItems === []): ?>
                <p class="admin-media-empty">Inga bilder har lagts till ännu.</p>
            <?php else: ?>
                <form method="post" action="<?= $escape($mediaSortAction) ?>">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">

                    <div class="admin-media-grid">
                        <?php foreach ($mediaItems as $mediaItem): ?>
                            <?php if (!is_array($mediaItem)) {
                                continue;
                            } ?>
                            <?php
                            $mediaPublicId = (string) ($mediaItem['media_public_id'] ?? '');
                            $thumbnailUrl = '/admin/media/' . rawurlencode($mediaPublicId) . '/thumbnail';
                            $isPrimary = (int) ($mediaItem['is_primary'] ?? 0) === 1;
                            $mimeType = (string) ($mediaItem['mime_type'] ?? '');
                            $fileSize = $formatBytes($mediaItem['file_size_bytes'] ?? null);
                            $dimensions = is_numeric($mediaItem['width'] ?? null) && is_numeric($mediaItem['height'] ?? null)
                                ? (string) ((int) $mediaItem['width']) . ' x ' . (string) ((int) $mediaItem['height']) . ' px'
                                : '';
                            $metadata = array_filter([$mimeType, $fileSize, $dimensions], static fn (string $value): bool => $value !== '');
                            ?>
                            <article class="admin-media-card<?= $isPrimary ? ' is-primary' : '' ?>">
                                <img
                                    src="<?= $escape($thumbnailUrl) ?>"
                                    alt="<?= $escape($isPrimary ? 'Huvudbild för objektet' : 'Objektbild') ?>"
                                    loading="lazy"
                                >

                                <div>
                                    <?php if ($isPrimary): ?>
                                        <span class="admin-media-badge" aria-label="Markerad som huvudbild">Huvudbild</span>
                                    <?php else: ?>
                                        <strong>Bild</strong>
                                    <?php endif; ?>
                                    <p><?= $escape($isPrimary ? 'Visas först publikt' : 'Aktiv objektbild') ?></p>
                                    <?php if ($metadata !== []): ?>
                                        <p class="admin-media-meta"><?= $escape(implode(' · ', $metadata)) ?></p>
                                    <?php endif; ?>
                                </div>

                                <label>
                                    <span>Sortering</span>
                                    <input
                                        name="sort_order[<?= $escape($mediaPublicId) ?>]"
                                        type="number"
                                        min="0"
                                        max="10000"
                                        step="1"
                                        value="<?= $escape($mediaItem['sort_order'] ?? 0) ?>"
                                    >
                                </label>

                                <div class="admin-media-card-actions">
                                    <?php if (!$isPrimary): ?>
                                        <button
                                            class="admin-button admin-button-secondary"
                                            type="submit"
                                            form="set-primary-<?= $escape($mediaPublicId) ?>"
                                            aria-label="Sätt bilden som huvudbild"
                                        >
                                            Sätt primär
                                        </button>
                                    <?php endif; ?>
                                    <button
                                        class="admin-button admin-button-danger"
                                        type="submit"
                                        form="archive-media-<?= $escape($mediaPublicId) ?>"
                                        aria-label="Arkivera bilden"
                                    >
                                        Arkivera
                                    </button>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <div class="admin-actions">
                        <button class="admin-button admin-button-secondary" type="submit">Spara bildordning</button>
                    </div>
                </form>

                <?php foreach ($mediaItems as $mediaItem): ?>
                    <?php if (!is_array($mediaItem)) {
                        continue;
                    } ?>
                    <?php $mediaPublicId = (string) ($mediaItem['media_public_id'] ?? ''); ?>
                    <form
                        id="set-primary-<?= $escape($mediaPublicId) ?>"
                        method="post"
                        action="/admin/items/<?= rawurlencode($itemPublicId) ?>/media/<?= rawurlencode($mediaPublicId) ?>/primary"
                    >
                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    </form>
                    <form
                        id="archive-media-<?= $escape($mediaPublicId) ?>"
                        method="post"
                        action="/admin/items/<?= rawurlencode($itemPublicId) ?>/media/<?= rawurlencode($mediaPublicId) ?>/archive"
                    >
                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    </form>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</section>
