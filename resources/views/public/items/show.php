<?php

$item = is_array($item ?? null) ? $item : [];
$images = is_array($images ?? null) ? $images : [];

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$stringValue = static fn (mixed $value): string => is_scalar($value) ? trim((string) $value) : '';
$formatAmount = static function (mixed $amount): string {
    $numericAmount = is_numeric($amount) ? (float) $amount : 0.0;
    $decimals = abs($numericAmount - round($numericAmount)) > 0.00001 ? 2 : 0;

    return number_format($numericAmount, $decimals, ',', ' ');
};
$currencyCode = static function (mixed $currency) use ($stringValue): string {
    return $stringValue($currency) === '' ? 'SEK' : strtoupper($stringValue($currency));
};

$name = $stringValue($item['name'] ?? '');
$shortName = $stringValue($item['short_name'] ?? '');
$description = $stringValue($item['description'] ?? '');
$categoryName = $stringValue($item['primary_category_name'] ?? '');
$organizationName = $stringValue($item['organization_name'] ?? '');
$dailyRateCurrency = $currencyCode($item['daily_rate_currency'] ?? 'SEK');
$dailyRate = $formatAmount($item['daily_rate_amount'] ?? null) . ' ' . $dailyRateCurrency . '/dag';
$depositAmount = $item['deposit_amount'] ?? null;
$hasDeposit = is_numeric($depositAmount) && (float) $depositAmount > 0.0;
$deposit = $hasDeposit ? $formatAmount($depositAmount) . ' ' . $dailyRateCurrency : '';
$publicId = $stringValue($item['public_id'] ?? '');
$slug = $stringValue($item['slug'] ?? '');
$bookingUrl = $publicId !== '' && $slug !== ''
    ? '/items/' . rawurlencode($publicId) . '/' . rawurlencode($slug) . '/book'
    : '';
$mainImage = is_array($images[0] ?? null) ? $images[0] : null;
?>
<section>
    <p class="public-back-link"><a href="/items">Tillbaka till objekt</a></p>

    <article class="public-detail">
        <div class="public-page-header">
            <h1><?= $escape($name) ?></h1>
            <?php if ($shortName !== ''): ?>
                <p><?= $escape($shortName) ?></p>
            <?php endif; ?>
        </div>

        <?php if ($mainImage !== null && $stringValue($mainImage['url'] ?? '') !== ''): ?>
            <?php $mainImageUrl = $stringValue($mainImage['url'] ?? ''); ?>
            <figure class="public-detail-main-image">
                <img
                    id="huvudbild"
                    src="<?= $escape($mainImageUrl) ?>"
                    alt="<?= $escape($name . ' - huvudbild') ?>"
                >
            </figure>

            <?php if (count($images) > 1): ?>
                <nav class="public-detail-gallery" aria-label="Objektbilder">
                    <?php foreach ($images as $index => $image): ?>
                        <?php if (!is_array($image)) {
                            continue;
                        } ?>
                        <?php
                        $imageUrl = $stringValue($image['url'] ?? '');
                        $imagePublicId = $stringValue($image['public_id'] ?? '');
                        $thumbnailUrl = $imagePublicId !== ''
                            ? '/media/' . rawurlencode($imagePublicId) . '/thumbnail'
                            : $imageUrl;
                        $imageNumber = $index + 1;
                        ?>
                        <?php if ($imageUrl !== ''): ?>
                            <a
                                class="public-detail-gallery-link"
                                href="<?= $escape($imageUrl) ?>"
                                aria-label="Öppna bild <?= $escape((string) $imageNumber) ?> för <?= $escape($name) ?>"
                            >
                                <img
                                    src="<?= $escape($thumbnailUrl) ?>"
                                    alt="<?= $escape($name . ' - bild ' . (string) $imageNumber) ?>"
                                    loading="lazy"
                                >
                                <?php if ((bool) ($image['is_primary'] ?? false)): ?>
                                    <span>Huvudbild</span>
                                <?php else: ?>
                                    <span>Bild <?= $escape((string) $imageNumber) ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <div
                class="public-detail-image-placeholder"
                role="img"
                aria-label="Bild saknas för <?= $escape($name) ?>"
            >
                <span>Bild saknas</span>
            </div>
        <?php endif; ?>

        <div class="public-detail-grid">
            <div>
                <p class="public-item-meta">
                    <?php if ($categoryName !== ''): ?>
                        <?= $escape($categoryName) ?>
                    <?php endif; ?>
                    <?php if ($categoryName !== '' && $organizationName !== ''): ?>
                        &middot;
                    <?php endif; ?>
                    <?php if ($organizationName !== ''): ?>
                        <?= $escape($organizationName) ?>
                    <?php endif; ?>
                </p>

                <?php if ($description !== ''): ?>
                    <p class="public-item-description"><?= nl2br($escape($description)) ?></p>
                <?php endif; ?>
            </div>

            <aside class="public-detail-panel" aria-label="Pris">
                <p class="public-detail-label">Pris</p>
                <p class="public-detail-price"><?= $escape($dailyRate) ?></p>

                <?php if ($hasDeposit): ?>
                    <p class="public-detail-deposit">
                        Deposition: <?= $escape($deposit) ?>
                    </p>
                <?php endif; ?>

                <?php if ($bookingUrl !== ''): ?>
                    <a class="public-primary-link" href="<?= $escape($bookingUrl) ?>">Boka objekt</a>
                <?php endif; ?>
            </aside>
        </div>
    </article>
</section>
