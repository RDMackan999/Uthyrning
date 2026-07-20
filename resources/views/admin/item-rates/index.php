<?php

$item = is_array($item ?? null) ? $item : [];
$rates = is_array($rates ?? null) ? $rates : [];
$csrfToken = is_string($csrfToken ?? null) ? $csrfToken : '';
$message = is_string($message ?? null) ? $message : null;
$publicId = (string) ($item['public_id'] ?? '');

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$yesNo = static fn (mixed $value): string => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Ja' : 'Nej';
$rateTypeLabel = static fn (mixed $value): string => match ((string) $value) {
    'daily' => 'Dagspris',
    'weekend' => 'Helgpris',
    'weekly' => 'Veckopris',
    'monthly' => 'Månadspris',
    default => (string) $value,
};
?>
<section class="admin-panel">
    <div class="admin-page-header">
        <div>
            <h1>Priser</h1>
            <p><?= $escape($item['name'] ?? '') ?></p>
        </div>

        <div class="admin-actions">
            <a class="admin-button admin-button-secondary" href="/admin/items/<?= rawurlencode($publicId) ?>/edit">Till objektet</a>
            <a class="admin-button" href="/admin/items/<?= rawurlencode($publicId) ?>/rates/create">Nytt pris</a>
        </div>
    </div>

    <?php if ($message !== null): ?>
        <p class="admin-message" role="status"><?= $escape($message) ?></p>
    <?php endif; ?>

    <?php if ($rates === []): ?>
        <p>Inga priser finns upplagda ännu.</p>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Typ</th>
                        <th>Belopp</th>
                        <th>Valuta</th>
                        <th>Aktiv</th>
                        <th>Skapad</th>
                        <th>Uppdaterad</th>
                        <th>Åtgärd</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rates as $rate): ?>
                        <?php if (!is_array($rate)) {
                            continue;
                        } ?>
                        <tr>
                            <td><?= $escape($rateTypeLabel($rate['rate_type'] ?? '')) ?></td>
                            <td><?= $escape($rate['amount'] ?? '') ?></td>
                            <td><?= $escape($rate['currency'] ?? '') ?></td>
                            <td><?= $escape($yesNo($rate['is_active'] ?? false)) ?></td>
                            <td><?= $escape($rate['created_at'] ?? '') ?></td>
                            <td><?= $escape($rate['updated_at'] ?? '') ?></td>
                            <td>
                                <div class="admin-inline-actions">
                                    <a href="/admin/items/<?= rawurlencode($publicId) ?>/rates/<?= rawurlencode((string) ($rate['id'] ?? '')) ?>/edit">Redigera</a>
                                    <form method="post" action="/admin/items/<?= rawurlencode($publicId) ?>/rates/<?= rawurlencode((string) ($rate['id'] ?? '')) ?>/archive">
                                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                        <button class="admin-link-button" type="submit">Arkivera</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
