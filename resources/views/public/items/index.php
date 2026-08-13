<?php

$items = is_array($items ?? null) ? $items : [];

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$stringValue = static fn (mixed $value): string => is_scalar($value) ? trim((string) $value) : '';
$formatPrice = static function (mixed $amount, mixed $currency) use ($stringValue): string {
    $numericAmount = is_numeric($amount) ? (float) $amount : 0.0;
    $formattedAmount = number_format($numericAmount, 0, ',', ' ');
    $currencyCode = $stringValue($currency) === '' ? 'SEK' : strtoupper($stringValue($currency));

    return $formattedAmount . ' ' . $currencyCode . '/dag';
};
?>
<section>
    <div class="public-page-header">
        <h1>Hyr objekt</h1>
        <p>Publicerade verktyg, maskiner och utrustning som Ã¤r tillgÃ¤ngliga fÃ¶r uthyrning.</p>
    </div>

    <?php if ($items === []): ?>
        <div class="public-empty-state">
            <p>Inga objekt finns tillgÃ¤ngliga fÃ¶r uthyrning just nu.</p>
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
