<?php

$assignments = is_array($assignments ?? null) ? $assignments : [];
$message = is_string($message ?? null) ? $message : null;
$error = is_string($error ?? null) ? $error : null;
$csrfToken = is_string($csrfToken ?? null) ? $csrfToken : '';

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$displayName = static function (array $assignment): string {
    $name = trim(implode(' ', array_filter([
        is_string($assignment['first_name'] ?? null) ? $assignment['first_name'] : '',
        is_string($assignment['last_name'] ?? null) ? $assignment['last_name'] : '',
    ])));

    return $name !== '' ? $name : (string) ($assignment['email'] ?? '');
};
?>
<section class="admin-panel">
    <div class="admin-page-header">
        <div>
            <h1>Organisationsadministratörer</h1>
            <p>Hantera endast rollen organization_admin för befintliga användare.</p>
        </div>
        <a class="admin-button" href="/admin/organization-admins/assign">Tilldela roll</a>
    </div>

    <?php if ($message !== null): ?>
        <p class="admin-message"><?= $escape($message) ?></p>
    <?php endif; ?>

    <?php if ($error !== null): ?>
        <p class="admin-error"><?= $escape($error) ?></p>
    <?php endif; ?>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Användare</th>
                    <th>E-post</th>
                    <th>Organisation</th>
                    <th>Tilldelad</th>
                    <th>Åtgärd</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($assignments === []): ?>
                    <tr>
                        <td colspan="5">Inga organisationsadministratörer finns ännu.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($assignments as $assignment): ?>
                    <?php
                    $userId = (int) ($assignment['user_id'] ?? 0);
                    $organizationId = (int) ($assignment['organization_id'] ?? 0);
                    ?>
                    <tr>
                        <td><?= $escape($displayName($assignment)) ?></td>
                        <td><?= $escape((string) ($assignment['email'] ?? '')) ?></td>
                        <td><?= $escape((string) ($assignment['organization_name'] ?? '')) ?></td>
                        <td><?= $escape((string) ($assignment['assigned_at'] ?? '')) ?></td>
                        <td>
                            <?php if ($userId > 0 && $organizationId > 0): ?>
                                <form method="post" action="/admin/organization-admins/<?= $userId ?>/<?= $organizationId ?>/revoke">
                                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                    <button class="admin-link-button" type="submit">Återkalla</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
