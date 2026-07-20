<?php

$items = is_array($items ?? null) ? $items : [];
$message = is_string($message ?? null) ? $message : null;

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$yesNo = static fn (mixed $value): string => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Ja' : 'Nej';
?>
<section class="admin-panel">
    <div class="admin-page-header">
        <div>
            <h1>Objekt</h1>
            <p>Hantera uthyrningsobjekt för administration.</p>
        </div>

        <a class="admin-button" href="/admin/items/create">Nytt objekt</a>
    </div>

    <?php if ($message !== null): ?>
        <p class="admin-message" role="status"><?= $escape($message) ?></p>
    <?php endif; ?>

    <?php if ($items === []): ?>
        <p>Inga objekt finns upplagda ännu.</p>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Namn</th>
                        <th>Public ID</th>
                        <th>Kategori</th>
                        <th>Organisation</th>
                        <th>Aktiv</th>
                        <th>Uthyrningsbar</th>
                        <th>Åtgärd</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <?php if (!is_array($item)) {
                            continue;
                        } ?>
                        <tr>
                            <td><?= $escape($item['name'] ?? '') ?></td>
                            <td><code><?= $escape($item['public_id'] ?? '') ?></code></td>
                            <td><?= $escape($item['primary_category_name'] ?? '') ?></td>
                            <td><?= $escape($item['organization_name'] ?? '') ?></td>
                            <td><?= $escape($yesNo($item['is_active'] ?? false)) ?></td>
                            <td><?= $escape($yesNo($item['is_rentable'] ?? false)) ?></td>
                            <td>
                                <a href="/admin/items/<?= rawurlencode((string) ($item['public_id'] ?? '')) ?>/edit">Redigera</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
