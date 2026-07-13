<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Logger;
use App\Services\AdminProvisioningService;

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

echo 'Create first administrator' . PHP_EOL;
echo 'No password is accepted through command-line arguments.' . PHP_EOL;

try {
    $email = readRequiredLine('E-post: ');
    $displayName = readRequiredLine('Visningsnamn: ');
    $password = readPassword('Lösenord: ');
    $passwordConfirmation = readPassword('Bekräfta lösenord: ');
    $organizationName = readRequiredLine('Organisationens namn: ');
    $companyName = readRequiredLine('Företagets namn: ');

    if ($password !== $passwordConfirmation) {
        fwrite(STDERR, 'Passwords do not match.' . PHP_EOL);
        exit(1);
    }

    $service = new AdminProvisioningService();
    $result = $service->provision(
        $email,
        $displayName,
        $password,
        $organizationName,
        $companyName
    );

    unset($password, $passwordConfirmation);

    $organization = $result['organization']->toArray();
    $company = $result['company']->toArray();
    $user = $result['user']->toArray();
    $role = $result['role']->toArray();

    echo 'Administrator created' . PHP_EOL;
    echo 'Organization ID: ' . (string) ($organization['id'] ?? '') . PHP_EOL;
    echo 'Company ID: ' . (string) ($company['id'] ?? '') . PHP_EOL;
    echo 'User ID: ' . (string) ($user['id'] ?? '') . PHP_EOL;
    echo 'Role: ' . (string) ($role['role_key'] ?? '') . PHP_EOL;
    exit(0);
} catch (Throwable $exception) {
    unset($password, $passwordConfirmation);

    $logger->error('Create admin command failed', [
        'exception' => $exception::class,
        'code' => $exception->getCode(),
    ]);

    fwrite(STDERR, 'Could not create administrator. ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

/**
 * Read a required non-secret line from STDIN.
 */
function readRequiredLine(string $prompt): string
{
    $value = trim(readPlainLine($prompt));

    if ($value === '') {
        throw new InvalidArgumentException('All fields are required.');
    }

    return $value;
}

/**
 * Read a password without command-line arguments.
 */
function readPassword(string $prompt): string
{
    if (canHideTerminalInput()) {
        fwrite(STDOUT, $prompt);
        shell_exec('stty -echo');

        try {
            $value = fgets(STDIN);
        } finally {
            shell_exec('stty echo');
            fwrite(STDOUT, PHP_EOL);
        }

        return trim($value === false ? '' : $value);
    }

    fwrite(STDERR, 'Warning: hidden password input is not available in this terminal. Input will be visible.' . PHP_EOL);

    return trim(readPlainLine($prompt));
}

/**
 * Read one plain line.
 */
function readPlainLine(string $prompt): string
{
    fwrite(STDOUT, $prompt);
    $value = fgets(STDIN);

    return $value === false ? '' : $value;
}

/**
 * Determine whether the current terminal supports stty-based hidden input.
 */
function canHideTerminalInput(): bool
{
    if (PHP_OS_FAMILY === 'Windows') {
        return false;
    }

    if (!function_exists('stream_isatty') || !stream_isatty(STDIN)) {
        return false;
    }

    return trim((string) shell_exec('command -v stty')) !== '';
}
