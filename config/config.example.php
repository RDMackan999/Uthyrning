<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'Uthyrning',
        'environment' => 'development',
        'debug' => true,
        'timezone' => 'Europe/Stockholm',
        'base_url' => 'http://localhost',
        'version' => '0.1.0',
    ],
    'auth' => [
        'session_cookie_name' => 'uthyrning_session',
        'session_cookie_lifetime' => 28800,
        'csrf_cookie_name' => 'uthyrning_csrf',
        'csrf_token_lifetime' => 1800,
    ],
];
