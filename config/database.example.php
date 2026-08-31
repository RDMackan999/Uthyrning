<?php

declare(strict_types=1);

return [
    'database' => [
        // Local runtime overrides can use DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD and DB_CHARSET.
        // Use a dedicated database such as uthyrning_test together with APP_ENV=test for tests/run.php.
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'uthyrning_dev',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
];
