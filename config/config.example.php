<?php

declare(strict_types=1);

return [
    'app' => [
        // Local runtime overrides can use APP_ENV, APP_DEBUG, APP_TIMEZONE and APP_BASE_URL.
        // Database-writing tests must be run with APP_ENV=test and a dedicated test database.
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
    'notifications' => [
        'email_transport' => 'development',
        'max_attempts' => 3,
        'development_simulate_failure' => false,
        'smtp' => [
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => '',
            'password' => '',
            'from_address' => 'no-reply@example.com',
            'from_name' => 'Uthyrning',
            'timeout_seconds' => 10,
        ],
    ],
    'media' => [
        'storage_disk' => 'local',
        'local_root' => 'storage/media',
        'max_file_size_bytes' => 8388608,
        'max_width' => 6000,
        'max_height' => 6000,
        'variants' => [
            'thumbnail' => [
                'width' => 320,
                'height' => 240,
            ],
            'card' => [
                'width' => 800,
                'height' => 600,
            ],
            'detail' => [
                'width' => 1600,
                'height' => 1200,
            ],
        ],
    ],
];
