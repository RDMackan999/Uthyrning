<?php

$displayName = is_string($displayName ?? null) && $displayName !== '' ? $displayName : 'okänd användare';
$email = is_string($email ?? null) ? $email : '';

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<section class="admin-panel">
    <h1>Admin</h1>

    <p>Inloggad som <?= $escape($displayName !== '' ? $displayName : $email) ?></p>

    <div class="status-grid" aria-label="Status">
        <div class="status-box">
            <strong>Authentication</strong>
            <div>OK</div>
        </div>

        <div class="status-box">
            <strong>Authorization</strong>
            <div>system_admin</div>
        </div>
    </div>

    <p>Administrationsfunktioner byggs i kommande sprintar.</p>
</section>
