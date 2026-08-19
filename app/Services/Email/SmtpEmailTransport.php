<?php

declare(strict_types=1);

namespace App\Services\Email;

use App\Contracts\EmailTransportInterface;
use App\Core\NotificationException;
use Closure;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use Throwable;

/**
 * Generic provider-neutral SMTP email transport.
 */
final class SmtpEmailTransport implements EmailTransportInterface
{
    private const ENCRYPTION_STARTTLS = 'tls';
    private const ENCRYPTION_SMTPS = 'ssl';

    /**
     * @param Closure(): PHPMailer|null $mailerFactory
     */
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $encryption,
        private readonly ?string $username,
        private readonly ?string $password,
        private readonly string $fromAddress,
        private readonly string $fromName,
        private readonly int $timeoutSeconds = 10,
        private readonly EmailMessageValidator $validator = new EmailMessageValidator(),
        private readonly ?Closure $mailerFactory = null,
    ) {
        $this->validateConfiguration();
    }

    /**
     * Create SMTP transport from application config values.
     *
     * @param array<string, mixed> $config
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            self::requiredString($config['host'] ?? null),
            self::requiredPort($config['port'] ?? null),
            self::requiredEncryption($config['encryption'] ?? null),
            self::nullableString($config['username'] ?? null),
            self::nullableString($config['password'] ?? null),
            self::requiredString($config['from_address'] ?? null),
            self::requiredString($config['from_name'] ?? null),
            self::requiredTimeout($config['timeout_seconds'] ?? 10),
        );
    }

    public function send(EmailMessage $message): EmailDeliveryResult
    {
        try {
            $this->validator->validateMessage($message);
            $mailer = $this->configuredMailer();
            $mailer->addAddress($message->recipientEmail);
            $mailer->Subject = $message->subject;
            $mailer->isHTML(true);
            $mailer->Body = $message->htmlBody;
            $mailer->AltBody = $message->textBody;

            if (!$mailer->send()) {
                return EmailDeliveryResult::failure($this->safeErrorCode($mailer->ErrorInfo), 'SMTP delivery failed.');
            }

            return EmailDeliveryResult::success($this->messageId($mailer));
        } catch (NotificationException $exception) {
            throw $exception;
        } catch (PHPMailerException $exception) {
            return EmailDeliveryResult::failure($this->safeErrorCode($exception->getMessage()), 'SMTP delivery failed.');
        } catch (Throwable) {
            return EmailDeliveryResult::failure('delivery_failed', 'SMTP delivery failed.');
        }
    }

    private function configuredMailer(): PHPMailer
    {
        $mailer = $this->createMailer();
        $mailer->CharSet = PHPMailer::CHARSET_UTF8;
        $mailer->isSMTP();
        $mailer->Host = $this->host;
        $mailer->Port = $this->port;
        $mailer->Timeout = $this->timeoutSeconds;
        $mailer->SMTPAutoTLS = true;
        $mailer->SMTPDebug = 0;
        $mailer->SMTPSecure = $this->encryption === self::ENCRYPTION_SMTPS
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;

        if ($this->username !== null || $this->password !== null) {
            if ($this->username === null || $this->password === null) {
                throw NotificationException::configurationError();
            }

            $mailer->SMTPAuth = true;
            $mailer->Username = $this->username;
            $mailer->Password = $this->password;
        }

        $mailer->setFrom($this->fromAddress, $this->fromName);

        return $mailer;
    }

    private function createMailer(): PHPMailer
    {
        if ($this->mailerFactory === null) {
            return new PHPMailer(true);
        }

        $mailer = ($this->mailerFactory)();

        if (!$mailer instanceof PHPMailer) {
            throw NotificationException::configurationError();
        }

        return $mailer;
    }

    private function validateConfiguration(): void
    {
        if (trim($this->host) === '' || !$this->isHostSafe($this->host)) {
            throw NotificationException::configurationError();
        }

        if ($this->port < 1 || $this->port > 65535) {
            throw NotificationException::configurationError();
        }

        if (!in_array($this->encryption, [self::ENCRYPTION_STARTTLS, self::ENCRYPTION_SMTPS], true)) {
            throw NotificationException::configurationError();
        }

        if ($this->username !== null && !$this->isHeaderSafe($this->username)) {
            throw NotificationException::configurationError();
        }

        if ($this->password !== null && !$this->isHeaderSafe($this->password)) {
            throw NotificationException::configurationError();
        }

        if (($this->username === null) !== ($this->password === null)) {
            throw NotificationException::configurationError();
        }

        $this->validator->validateAddress($this->fromAddress, 'from address');
        $this->validator->validateHeader($this->fromName, 'from name');
    }

    private function safeErrorCode(string $errorText): string
    {
        $text = strtolower($errorText);

        if (str_contains($text, 'auth')) {
            return 'authentication_failed';
        }

        if (str_contains($text, 'tls') || str_contains($text, 'ssl') || str_contains($text, 'certificate')) {
            return 'tls_failed';
        }

        if (str_contains($text, 'recipient') || str_contains($text, 'address')) {
            return 'recipient_rejected';
        }

        if (str_contains($text, 'connect') || str_contains($text, 'network') || str_contains($text, 'timeout')) {
            return 'connection_failed';
        }

        return 'delivery_failed';
    }

    private function messageId(PHPMailer $mailer): ?string
    {
        if (!method_exists($mailer, 'getLastMessageID')) {
            return null;
        }

        $messageId = $mailer->getLastMessageID();

        return is_string($messageId) && $messageId !== '' ? $messageId : null;
    }

    private function isHostSafe(string $value): bool
    {
        return $this->isHeaderSafe($value)
            && preg_match('/^[A-Za-z0-9.-]+$/', $value) === 1
            && trim($value, '.-') !== '';
    }

    private function isHeaderSafe(string $value): bool
    {
        return !str_contains($value, "\r") && !str_contains($value, "\n");
    }

    private static function requiredString(mixed $value): string
    {
        $text = trim((string) $value);

        if ($text === '') {
            throw NotificationException::configurationError();
        }

        return $text;
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private static function requiredPort(mixed $value): int
    {
        if (!is_numeric($value)) {
            throw NotificationException::configurationError();
        }

        $port = (int) $value;

        if ($port < 1 || $port > 65535) {
            throw NotificationException::configurationError();
        }

        return $port;
    }

    private static function requiredEncryption(mixed $value): string
    {
        $encryption = strtolower(trim((string) $value));

        return match ($encryption) {
            'tls', 'starttls' => self::ENCRYPTION_STARTTLS,
            'ssl', 'smtps' => self::ENCRYPTION_SMTPS,
            default => throw NotificationException::configurationError(),
        };
    }

    private static function requiredTimeout(mixed $value): int
    {
        if (!is_numeric($value)) {
            throw NotificationException::configurationError();
        }

        return max(1, min(60, (int) $value));
    }
}
