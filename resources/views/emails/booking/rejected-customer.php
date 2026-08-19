<h1>Din bokningsförfrågan kunde inte godkännas</h1>

<p>Hej <?= $e($context['customer_name'] ?? ''); ?>,</p>

<p>Din bokningsförfrågan kunde tyvärr inte godkännas.</p>

<ul>
    <li>Bokningsreferens: <?= $e($context['public_id'] ?? ''); ?></li>
    <li>Objekt: <?= $e($context['rental_item_name'] ?? ''); ?></li>
    <li>Startdatum: <?= $e($context['start_date'] ?? ''); ?></li>
    <li>Slutdatum: <?= $e($context['end_date'] ?? ''); ?></li>
    <li>Uthyrare: <?= $e($context['organization_name'] ?? ''); ?></li>
</ul>

<p>Kontakta uthyraren om du vill fråga om andra datum eller alternativ.</p>
