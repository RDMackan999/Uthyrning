<?php

$items = is_array($items ?? null) ? $items : [];
$categories = is_array($categories ?? null) ? $categories : [];
$filters = is_array($filters ?? null) ? $filters : [];
$hasActiveFilters = (bool) ($hasActiveFilters ?? false);
$resultCount = is_int($resultCount ?? null) ? $resultCount : count($items);

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$stringValue = static fn (mixed $value): string => is_scalar($value) ? trim((string) $value) : '';
$formatPrice = static function (mixed $amount, mixed $currency) use ($stringValue): string {
    $numericAmount = is_numeric($amount) ? (float) $amount : 0.0;
    $formattedAmount = number_format($numericAmount, 0, ',', ' ');
    $currencyCode = $stringValue($currency) === '' ? 'SEK' : strtoupper($stringValue($currency));

    return $formattedAmount . ' ' . $currencyCode . '/dag';
};

$searchQuery = $stringValue($filters['q'] ?? '');
$selectedCategory = $stringValue($filters['category'] ?? '');
?>
<section>
    <div class="public-page-header">
        <h1>Hyr objekt</h1>
        <p>Publicerade verktyg, maskiner och utrustning som &auml;r tillg&auml;ngliga f&ouml;r uthyrning.</p>
    </div>

    <form class="public-filter-form" action="/items" method="get">
        <div class="public-filter-field">
            <label for="public-item-search">Vad vill du hyra?</label>
            <input
                id="public-item-search"
                name="q"
                type="search"
                maxlength="100"
                value="<?= $escape($searchQuery) ?>"
            >
        </div>

        <div class="public-filter-field">
            <label for="public-item-category">Kategori</label>
            <select id="public-item-category" name="category">
                <option value="">Alla kategorier</option>
                <?php foreach ($categories as $category): ?>
                    <?php if (!is_array($category)) {
                        continue;
                    } ?>
                    <?php
                    $categorySlug = $stringValue($category['slug'] ?? '');
                    $categoryName = $stringValue($category['name'] ?? '');
                    ?>
                    <?php if ($categorySlug !== '' && $categoryName !== ''): ?>
                        <option value="<?= $escape($categorySlug) ?>" <?= $categorySlug === $selectedCategory ? 'selected' : '' ?>>
                            <?= $escape($categoryName) ?>
                        </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="public-filter-actions">
            <button type="submit">S&ouml;k</button>
            <?php if ($hasActiveFilters): ?>
                <a href="/items">Rensa filter</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($hasActiveFilters): ?>
        <p class="public-filter-summary">
            <?= $escape((string) $resultCount) ?> tr&auml;ffar
            <?php if ($searchQuery !== ''): ?>
                f&ouml;r "<?= $escape($searchQuery) ?>"
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <?php if ($items === []): ?>
        <div class="public-empty-state">
            <?php if ($hasActiveFilters): ?>
                <p>Inga objekt matchar din s&ouml;kning.</p>
            <?php else: ?>
                <p>Inga objekt finns tillg&auml;ngliga f&ouml;r uthyrning just nu.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="public-item-grid">
            <?php foreach ($items as $item): ?>
                <?php if (!is_array($item)) {
                    continue;
                } ?>
                <?php
                $description = $stringValue($item['description'] ?? null);
                $publicId = $stringValue($item['public_id'] ?? null);
                $slug = $stringValue($item['slug'] ?? null);
                $detailUrl = $publicId !== '' && $slug !== ''
                    ? '/items/' . rawurlencode($publicId) . '/' . rawurlencode($slug)
                    : '';
                ?>
                <article class="public-item-card">
                    <h2>
                        <?php if ($detailUrl !== ''): ?>
                            <a href="<?= $escape($detailUrl) ?>"><?= $escape($item['name'] ?? '') ?></a>
                        <?php else: ?>
                            <?= $escape($item['name'] ?? '') ?>
                        <?php endif; ?>
                    </h2>
                    <p class="public-item-meta">
                        <?= $escape($item['primary_category_name'] ?? '') ?>
                        <?php if ($stringValue($item['organization_name'] ?? null) !== ''): ?>
                            &middot; <?= $escape($item['organization_name'] ?? '') ?>
                        <?php endif; ?>
                    </p>

                    <?php if ($description !== ''): ?>
                        <p class="public-item-description"><?= $escape($description) ?></p>
                    <?php endif; ?>

                    <p class="public-item-price">
                        <?= $escape($formatPrice($item['daily_rate_amount'] ?? null, $item['daily_rate_currency'] ?? 'SEK')) ?>
                    </p>

                    <?php if ($detailUrl !== ''): ?>
                        <a class="public-detail-link" href="<?= $escape($detailUrl) ?>">Visa objekt</a>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
