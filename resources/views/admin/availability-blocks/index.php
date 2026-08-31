<?php

$item = is_array($item ?? null) ? $item : [];
$blocks = is_array($blocks ?? null) ? $blocks : [];
$reasonOptions = is_array($reasonOptions ?? null) ? $reasonOptions : [];
$csrfToken = is_string($csrfToken ?? null) ? $csrfToken : '';
$message = is_string($message ?? null) ? $message : null;

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$publicId = (string) ($item['public_id'] ?? '');
?>
<section class="admin-panel">
    <div class="admin-page-header">
        <div>
            <h1>Kalenderblockeringar</h1>
            <p>Hantera manuella perioder d&aring; <?= $escape($item['name'] ?? 'objektet') ?> inte kan bokas.</p>
        </div>

        <div class="admin-inline-actions">
            <a class="admin-button admin-button-secondary" href="/admin/items/<?= rawurlencode($publicId) ?>/edit">Till objektet</a>
            <a class="admin-button" href="/admin/items/<?= rawurlencode($publicId) ?>/availability/create">Ny blockering</a>
        </div>
    </div>

    <?php if ($message !== null): ?>
        <p class="admin-message"><?= $escape($message) ?></p>
    <?php endif; ?>

    <?php if ($blocks === []): ?>
        <p>Det finns inga aktiva kalenderblockeringar f&ouml;r objektet.</p>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Period</th>
                        <th>Typ</th>
                        <th>Intern notering</th>
                        <th>&Aring;tg&auml;rd</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($blocks as $block): ?>
                        <?php $blockId = (string) ($block['id'] ?? ''); ?>
                        <tr>
                            <td><?= $escape(($block['start_date'] ?? '') . ' - ' . ($block['end_date'] ?? '')) ?></td>
                            <td><?= $escape($reasonOptions[$block['reason_code'] ?? ''] ?? ($block['reason_code'] ?? '')) ?></td>
                            <td><?= $escape($block['internal_note'] ?? '') ?></td>
                            <td>
                                <form method="post" action="/admin/items/<?= rawurlencode($publicId) ?>/availability/<?= rawurlencode($blockId) ?>/archive">
                                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                    <button class="admin-link-button" type="submit">Arkivera</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
