<?php

$customers = is_array($customers ?? null) ? $customers : [];
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : [];
$statusFilter = is_string($statusFilter ?? null) ? $statusFilter : '';
$query = is_string($query ?? null) ? $query : '';
$message = is_string($message ?? null) ? $message : null;
$error = is_string($error ?? null) ? $error : null;

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$statusLabel = static fn (mixed $value): string => $statusOptions[(string) $value] ?? (string) $value;
$typeLabel = static fn (mixed $value): string => match ((string) $value) {
    'company' => 'Företag',
    default => 'Privatperson',
};
?>
<section class="admin-panel">
    <div class="admin-page-header">
        <div>
            <h1>Kunder</h1>
            <p>Organisationens kundregister från bokningsförfrågningar och adminunderhåll.</p>
        </div>
    </div>

    <?php if ($message !== null): ?>
        <p class="admin-message" role="status"><?= $escape($message) ?></p>
    <?php endif; ?>

    <?php if ($error !== null): ?>
        <p class="admin-error" role="alert"><?= $escape($error) ?></p>
    <?php endif; ?>

    <form class="admin-form" method="get" action="/admin/customers">
        <div class="admin-form-grid">
            <label>
                Sök
                <input type="search" name="q" value="<?= $escape($query) ?>">
            </label>

            <label>
                Status
                <select name="status">
                    <option value="">Alla statusar</option>
                    <?php foreach ($statusOptions as $value => $label): ?>
                        <option value="<?= $escape($value) ?>" <?= $statusFilter === (string) $value ? 'selected' : '' ?>>
                            <?= $escape($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <div class="admin-form-actions">
            <button class="admin-button" type="submit">Filtrera</button>
            <a class="admin-button admin-button-secondary" href="/admin/customers">Rensa</a>
        </div>
    </form>

    <?php if ($customers === []): ?>
        <p>Inga kunder hittades.</p>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Namn</th>
                        <th>Typ</th>
                        <th>Organisation</th>
                        <th>Företag</th>
                        <th>Status</th>
                        <th>Bokningar</th>
                        <th>Åtgärd</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $customer): ?>
                        <?php if (!is_array($customer)) {
                            continue;
                        } ?>
                        <?php $customerId = (string) ($customer['id'] ?? ''); ?>
                        <tr>
                            <td>
                                <strong><?= $escape($customer['name'] ?? '') ?></strong><br>
                                <?= $escape($customer['email'] ?? '-') ?><br>
                                <?= $escape($customer['phone'] ?? '-') ?>
                            </td>
                            <td><?= $escape($typeLabel($customer['customer_type_key'] ?? 'private')) ?></td>
                            <td><?= $escape($customer['organization_name'] ?? '-') ?></td>
                            <td><?= $escape($customer['company_name'] ?? '-') ?></td>
                            <td><?= $escape($statusLabel($customer['status_key'] ?? '')) ?></td>
                            <td><?= $escape((int) ($customer['booking_count'] ?? 0)) ?></td>
                            <td>
                                <div class="admin-inline-actions">
                                    <a href="/admin/customers/<?= rawurlencode($customerId) ?>">Visa</a>
                                    <a href="/admin/customers/<?= rawurlencode($customerId) ?>/edit">Redigera</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
