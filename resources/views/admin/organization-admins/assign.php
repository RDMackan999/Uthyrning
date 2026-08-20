<?php

$data = is_array($data ?? null) ? $data : [];
$errors = is_array($errors ?? null) ? $errors : [];
$users = is_array($users ?? null) ? $users : [];
$organizations = is_array($organizations ?? null) ? $organizations : [];
$csrfToken = is_string($csrfToken ?? null) ? $csrfToken : '';

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$selectedUserId = (string) ($data['user_id'] ?? '');
$selectedOrganizationId = (string) ($data['organization_id'] ?? '');
$query = is_string($data['q'] ?? null) ? $data['q'] : '';
$userLabel = static function (array $user): string {
    $name = trim(implode(' ', array_filter([
        is_string($user['first_name'] ?? null) ? $user['first_name'] : '',
        is_string($user['last_name'] ?? null) ? $user['last_name'] : '',
    ])));
    $email = (string) ($user['email'] ?? '');

    return $name === '' ? $email : $name . ' - ' . $email;
};
?>
<section class="admin-panel">
    <div class="admin-page-header">
        <div>
            <h1>Tilldela organisationsadministratör</h1>
            <p>Välj befintlig användare och organisation. Rollen sätts alltid server-side.</p>
        </div>
        <a class="admin-button admin-button-secondary" href="/admin/organization-admins">Tillbaka</a>
    </div>

    <?php if (isset($errors['form'])): ?>
        <p class="admin-error"><?= $escape((string) $errors['form']) ?></p>
    <?php endif; ?>

    <form class="admin-form" method="get" action="/admin/organization-admins/assign">
        <label>
            Sök användare
            <input type="search" name="q" value="<?= $escape($query) ?>" placeholder="Namn eller e-post">
        </label>
        <div class="admin-form-actions">
            <button class="admin-button admin-button-secondary" type="submit">Sök</button>
        </div>
    </form>

    <hr>

    <form class="admin-form" method="post" action="/admin/organization-admins">
        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">

        <label>
            Användare
            <select name="user_id" required>
                <option value="">Välj användare</option>
                <?php foreach ($users as $user): ?>
                    <?php $userId = (string) ($user['id'] ?? ''); ?>
                    <option value="<?= $escape($userId) ?>" <?= $selectedUserId === $userId ? 'selected' : '' ?>>
                        <?= $escape($userLabel($user)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            Organisation
            <select name="organization_id" required>
                <option value="">Välj organisation</option>
                <?php foreach ($organizations as $organization): ?>
                    <?php $organizationId = (string) ($organization['id'] ?? ''); ?>
                    <option value="<?= $escape($organizationId) ?>" <?= $selectedOrganizationId === $organizationId ? 'selected' : '' ?>>
                        <?= $escape((string) ($organization['name'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <div class="admin-form-actions">
            <button class="admin-button" type="submit">Tilldela organization_admin</button>
            <a class="admin-button admin-button-secondary" href="/admin/organization-admins">Avbryt</a>
        </div>
    </form>
</section>
