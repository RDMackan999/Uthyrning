<?php

$booking = is_array($booking ?? null) ? $booking : [];

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$stringValue = static fn (mixed $value): string => is_scalar($value) ? trim((string) $value) : '';
$formatAmount = static function (mixed $amount): string {
    $numericAmount = is_numeric($amount) ? (float) $amount : 0.0;
    $decimals = abs($numericAmount - round($numericAmount)) > 0.00001 ? 2 : 0;

    return number_format($numericAmount, $decimals, ',', ' ');
};
$currency = $stringValue($booking['currency'] ?? '') === '' ? 'SEK' : strtoupper($stringValue($booking['currency'] ?? 'SEK'));
$depositAmount = $booking['deposit_amount'] ?? null;
$hasDeposit = is_numeric($depositAmount) && (float) $depositAmount > 0.0;
?>
<section>
    <div class="public-page-header">
        <h1>Bokningsf&ouml;rfr&aringgan mottagen</h1>
        <p>Din bokningsf&ouml;rfr&aringgan &auml;r mottagen. Bokningen &auml;r inte bekr&auml;ftad f&ouml;rr&auml;n den har godk&auml;nts av uthyraren.</p>
    </div>

    <article class="public-detail">
        <div class="public-summary-list">
            <div class="public-summary-row">
                <span>Referens</span>
                <span><?= $escape($booking['public_id'] ?? '') ?></span>
            </div>
            <div class="public-summary-row">
                <span>Objekt</span>
                <span><?= $escape($booking['rental_item_name'] ?? '') ?></span>
            </div>
            <div class="public-summary-row">
                <span>Startdatum</span>
                <span><?= $escape($booking['start_date'] ?? '') ?></span>
            </div>
            <div class="public-summary-row">
                <span>Slutdatum</span>
                <span><?= $escape($booking['end_date'] ?? '') ?></span>
            </div>
            <div class="public-summary-row">
                <span>Antal dagar</span>
                <span><?= $escape($booking['total_units'] ?? '') ?></span>
            </div>
            <div class="public-summary-row">
                <span>Pris</span>
                <span><?= $escape($formatAmount($booking['subtotal_amount'] ?? null) . ' ' . $currency) ?></span>
            </div>
            <?php if ($hasDeposit): ?>
                <div class="public-summary-row">
                    <span>Deposition</span>
                    <span><?= $escape($formatAmount($depositAmount) . ' ' . $currency) ?></span>
                </div>
            <?php endif; ?>
        </div>

        <p class="public-back-link">
            <a href="/items">Tillbaka till objekt</a>
        </p>
    </article>
</section>
