<h1>Bokningsförfrågan mottagen</h1>

<p>Hej <?= $e($context['customer_name'] ?? ''); ?>,</p>

<p>Vi har tagit emot din bokningsförfrågan. Den är inte slutgiltigt godkänd förrän uthyraren har granskat den.</p>

<ul>
    <li>Bokningsreferens: <?= $e($context['public_id'] ?? ''); ?></li>
    <li>Objekt: <?= $e($context['rental_item_name'] ?? ''); ?></li>
    <li>Startdatum: <?= $e($context['start_date'] ?? ''); ?></li>
    <li>Slutdatum: <?= $e($context['end_date'] ?? ''); ?></li>
    <li>Uthyrare: <?= $e($context['organization_name'] ?? ''); ?></li>
    <li>Pris: <?= $e($money($context['subtotal_amount'] ?? null)); ?></li>
    <li>Deposition: <?= $e($money($context['deposit_amount'] ?? null)); ?></li>
</ul>

<p>Status: <?= $e($context['status_message'] ?? 'Bokningsförfrågan väntar på granskning.'); ?></p>

<p>Vi återkommer när förfrågan har granskats.</p>
