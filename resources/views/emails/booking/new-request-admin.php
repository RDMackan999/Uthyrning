<h1>Ny bokningsförfrågan</h1>

<p>En ny bokningsförfrågan har skickats in.</p>

<ul>
    <li>Bokningsreferens: <?= $e($context['public_id'] ?? ''); ?></li>
    <li>Objekt: <?= $e($context['rental_item_name'] ?? ''); ?></li>
    <li>Startdatum: <?= $e($context['start_date'] ?? ''); ?></li>
    <li>Slutdatum: <?= $e($context['end_date'] ?? ''); ?></li>
    <li>Kund: <?= $e($context['customer_name'] ?? ''); ?></li>
    <li>Kundens e-post: <?= $e($context['customer_email'] ?? ''); ?></li>
    <li>Pris: <?= $e($money($context['subtotal_amount'] ?? null)); ?></li>
    <li>Deposition: <?= $e($money($context['deposit_amount'] ?? null)); ?></li>
</ul>

<p>Logga in i admin för att granska och hantera förfrågan.</p>
