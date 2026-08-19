<?php

declare(strict_types=1);

namespace App\Services\Email;

use App\Contracts\EmailTransportInterface;
use App\Core\Config;
use App\Core\NotificationException;

/**
 * Resolves the configured email transport without leaking credentials.
 */
final class EmailTransportFactory
{
    /**
     * @param array<string, mixed>|null $notificationsConfig
     */
    public function __construct(
        private readonly ?array $notificationsConfig = null,
        private readonly ?string $environment = null,
    ) {
    }

    public function make(): EmailTransportInterface
    {
        $config = $this->notificationsConfig();
        $transportKey = $this->transportKey();
        $environment = $this->environment();

        if (in_array($transportKey, ['development', 'test'], true)) {
            if ($environment === 'production') {
                throw NotificationException::productionTransportNotConfigured();
            }

            return new DevelopmentEmailTransport((bool) ($config['development_simulate_failure'] ?? false));
        }

        if ($transportKey === 'smtp') {
            $smtpConfig = $config['smtp'] ?? [];

            if (!is_array($smtpConfig)) {
                throw NotificationException::configurationError();
            }

            return SmtpEmailTransport::fromConfig($smtpConfig);
        }

        throw new NotificationException('Configured email transport is not supported.', 'configuration_error');
    }

    public function transportKey(): string
    {
        $config = $this->notificationsConfig();
        $transportKey = strtolower(trim((string) ($config['email_transport'] ?? 'development')));

        return $transportKey === '' ? 'development' : $transportKey;
    }

    /**
     * @return array<string, mixed>
     */
    private function notificationsConfig(): array
    {
        if ($this->notificationsConfig !== null) {
            return $this->notificationsConfig;
        }

        $config = Config::get('notifications', []);

        return is_array($config) ? $config : [];
    }

    private function environment(): string
    {
        $environment = $this->environment ?? (string) Config::get('app.environment', 'production');

        return strtolower(trim($environment)) ?: 'production';
    }
}
