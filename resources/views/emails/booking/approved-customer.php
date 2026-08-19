<h1>Din bokning är godkänd</h1>

<p>Hej <?= $e($context['customer_name'] ?? ''); ?>,</p>

<p>Din bokning har godkänts.</p>

<ul>
    <li>Bokningsreferens: <?= $e($context['public_id'] ?? ''); ?></li>
    <li>Objekt: <?= $e($context['rental_item_name'] ?? ''); ?></li>
    <li>Startdatum: <?= $e($context['start_date'] ?? ''); ?></li>
    <li>Slutdatum: <?= $e($context['end_date'] ?? ''); ?></li>
    <li>Uthyrare: <?= $e($context['organization_name'] ?? ''); ?></li>
    <li>Pris: <?= $e($money($context['subtotal_amount'] ?? null)); ?></li>
    <li>Deposition: <?= $e($money($context['deposit_amount'] ?? null)); ?></li>
</ul>

<p>Kontakta uthyraren om du har frågor inför hämtning eller leverans.</p>
