<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Logger;
use App\Core\Migration;
use App\Core\MigrationRunner;

$basePath = dirname(__DIR__);
$autoloadPath = $basePath . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

if (is_file($autoloadPath)) {
    require $autoloadPath;
} else {
    spl_autoload_register(static function (string $class) use ($basePath): void {
        $prefix = 'App\\';

        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relativeClass = substr($class, strlen($prefix));
        $path = $basePath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR
            . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

        if (is_file($path)) {
            require $path;
        }
    });
}

Config::load($basePath);
date_default_timezone_set((string) Config::get('app.timezone', 'Europe/Stockholm'));

$logger = new Logger($basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs');
$runner = new MigrationRunner($basePath, $logger);

echo 'Migration started' . PHP_EOL;

try {
    $ran = $runner->run(static function (Migration $migration): void {
        echo 'Running ' . $migration->filename() . PHP_EOL;
    });

    if ($ran === []) {
        echo 'No pending migrations' . PHP_EOL;
    }

    echo 'Migration completed' . PHP_EOL;
    exit(0);
} catch (Throwable $exception) {
    $logger->error('Migration command failed', [
        'exception' => $exception::class,
        'code' => $exception->getCode(),
    ]);

    fwrite(STDERR, 'Migration failed' . PHP_EOL);
    exit(1);
}
