<?php

use App\Helpers\StatusLabels;

$customer = is_array($customer ?? null) ? $customer : [];
$data = is_array($data ?? null) ? $data : [];
$errors = is_array($errors ?? null) ? $errors : [];
$companies = is_array($companies ?? null) ? $companies : [];
$csrfToken = is_string($csrfToken ?? null) ? $csrfToken : '';
$message = is_string($message ?? null) ? $message : null;
$customerId = (string) ($customer['id'] ?? '');

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$fieldValue = static fn (string $key): string => (string) ($data[$key] ?? $customer[$key] ?? '');
$isSelected = static fn (string $key, string $value): string => $fieldValue($key) === $value ? 'selected' : '';
$statusLabel = static fn (mixed $value): string => StatusLabels::customer($value);
?>
<section class="admin-panel">
    <div class="admin-page-header">
        <div>
            <h1>Redigera kund</h1>
            <p><?= $escape($customer['name'] ?? '') ?></p>
        </div>

        <div class="admin-inline-actions">
            <a class="admin-button admin-button-secondary" href="/admin/customers/<?= rawurlencode($customerId) ?>">Till kund</a>
            <a class="admin-button admin-button-secondary" href="/admin/customers">Till kunder</a>
        </div>
    </div>

    <?php if ($message !== null): ?>
        <p class="admin-message" role="status"><?= $escape($message) ?></p>
    <?php endif; ?>

    <?php if (isset($errors['form'])): ?>
        <p class="admin-error" role="alert"><?= $escape($errors['form']) ?></p>
    <?php endif; ?>

    <h2>Identitet</h2>
    <div class="admin-readonly-grid">
        <div>
            <strong>Organisation</strong>
            <span><?= $escape($customer['organization_name'] ?? '-') ?></span>
        </div>
        <div>
            <strong>Status</strong>
            <span><?= $escape($statusLabel($customer['status_key'] ?? '')) ?></span>
        </div>
    </div>

    <form class="admin-form" method="post" action="/admin/customers/<?= rawurlencode($customerId) ?>">
        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">

        <div class="admin-form-grid">
            <label>
                Namn
                <input type="text" name="name" value="<?= $escape($fieldValue('name')) ?>" required>
                <?php if (isset($errors['name'])): ?>
                    <em><?= $escape($errors['name']) ?></em>
                <?php endif; ?>
            </label>

            <label>
                E-post
                <input type="email" name="email" value="<?= $escape($fieldValue('email')) ?>">
                <?php if (isset($errors['email'])): ?>
                    <em><?= $escape($errors['email']) ?></em>
                <?php endif; ?>
            </label>

            <label>
                Telefon
                <input type="text" name="phone" value="<?= $escape($fieldValue('phone')) ?>">
            </label>

            <label>
                Kundtyp
                <select name="customer_type_key">
                    <option value="private" <?= $isSelected('customer_type_key', 'private') ?>>Privatperson</option>
                    <option value="company" <?= $isSelected('customer_type_key', 'company') ?>>Företag</option>
                </select>
                <?php if (isset($errors['customer_type_key'])): ?>
                    <em><?= $escape($errors['customer_type_key']) ?></em>
                <?php endif; ?>
            </label>

            <label>
                Företag
                <select name="company_id">
                    <option value="">Inget kopplat företag</option>
                    <?php foreach ($companies as $company): ?>
                        <?php if (!is_array($company)) {
                            continue;
                        } ?>
                        <?php $companyId = (string) ($company['id'] ?? ''); ?>
                        <option value="<?= $escape($companyId) ?>" <?= $fieldValue('company_id') === $companyId ? 'selected' : '' ?>>
                            <?= $escape($company['name'] ?? '') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['company_id'])): ?>
                    <em><?= $escape($errors['company_id']) ?></em>
                <?php endif; ?>
            </label>
        </div>

        <div class="admin-form-actions">
            <button class="admin-button" type="submit">Spara kund</button>
        </div>
    </form>
</section>
