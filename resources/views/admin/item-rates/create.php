<?php

$item = is_array($item ?? null) ? $item : [];
$publicId = (string) ($item['public_id'] ?? '');
$formAction = '/admin/items/' . rawurlencode($publicId) . '/rates';
$formTitle = 'Nytt pris';
$submitLabel = 'Skapa pris';
$rate = null;

require __DIR__ . DIRECTORY_SEPARATOR . '_form.php';
