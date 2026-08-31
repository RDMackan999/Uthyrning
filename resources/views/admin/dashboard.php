<?php

$displayName = is_string($displayName ?? null) && $displayName !== '' ? $displayName : 'okänd användare';
$email = is_string($email ?? null) ? $email : '';
$authorizationLabel = is_string($authorizationLabel ?? null) ? $authorizationLabel : 'unknown';
$showSystemAdminNavigation = (bool) ($showSystemAdminNavigation ?? false);

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<section class="admin-panel">
    <h1>Admin</h1>

    <p>Inloggad som <?= $escape($displayName !== '' ? $displayName : $email) ?></p>

    <div class="status-grid" aria-label="Status">
        <div class="status-box">
            <strong>Inloggning</strong>
            <div>Aktiv</div>
        </div>

        <div class="status-box">
            <strong>Behörighet</strong>
            <div><?= $escape($authorizationLabel) ?></div>
        </div>
    </div>

    <p>Välj ett område i adminmenyn för att hantera objekt, bokningar, kunder eller notifieringar.</p>

    <?php if ($showSystemAdminNavigation): ?>
        <p><a class="admin-button" href="/admin/organization-admins">Organisationsadministratörer</a></p>
    <?php endif; ?>
</section>
