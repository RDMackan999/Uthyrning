<?php

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<section class="admin-panel public-empty-state" role="alert">
    <h1>Sidan hittades inte</h1>
    <p>Sidan kan ha flyttats, arkiverats eller saknas.</p>
    <p>
        <a class="admin-button public-primary-link" href="/items">Till objektkatalogen</a>
        <a class="admin-button admin-button-secondary public-detail-link" href="/admin">Till adminstart</a>
    </p>
</section>
