<h1>Din bokning är avbokad</h1>

<p>Hej <?= $e($context['customer_name'] ?? ''); ?>,</p>

<p>Din bokning är avbokad.</p>

<ul>
    <li>Bokningsreferens: <?= $e($context['public_id'] ?? ''); ?></li>
    <li>Objekt: <?= $e($context['rental_item_name'] ?? ''); ?></li>
    <li>Startdatum: <?= $e($context['start_date'] ?? ''); ?></li>
    <li>Slutdatum: <?= $e($context['end_date'] ?? ''); ?></li>
    <li>Uthyrare: <?= $e($context['organization_name'] ?? ''); ?></li>
</ul>

<p>Kontakta uthyraren om du har frågor om avbokningen.</p>
