<?php

$item = is_array($item ?? null) ? $item : [];
$rate = is_array($rate ?? null) ? $rate : [];
$publicId = (string) ($item['public_id'] ?? '');
$rateId = (string) ($rate['id'] ?? '');
$formAction = '/admin/items/' . rawurlencode($publicId) . '/rates/' . rawurlencode($rateId);
$formTitle = 'Redigera pris';
$submitLabel = 'Spara pris';

require __DIR__ . DIRECTORY_SEPARATOR . '_form.php';
