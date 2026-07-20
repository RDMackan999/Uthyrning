<?php

$item = is_array($item ?? null) ? $item : [];
$publicId = (string) ($item['public_id'] ?? '');
$formAction = '/admin/items/' . rawurlencode($publicId);
$formTitle = 'Redigera objekt';
$submitLabel = 'Spara ändringar';

require __DIR__ . DIRECTORY_SEPARATOR . '_form.php';
