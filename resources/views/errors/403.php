<?php

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<section class="admin-panel public-empty-state" role="alert">
    <h1>Du har inte behörighet</h1>
    <p>Du kan inte öppna den här sidan med din nuvarande behörighet.</p>
    <p>
        <a class="admin-button public-primary-link" href="/admin">Till adminstart</a>
        <a class="admin-button admin-button-secondary public-detail-link" href="/items">Till objektkatalogen</a>
    </p>
</section>
