<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\NotificationException;

/**
 * Renders file-based notification templates from resources/views/emails.
 */
final class NotificationTemplateService
{
    /**
     * @var array<string, array{subject: string, path: string}>
     */
    private const TEMPLATES = [
        'booking_request_received_customer' => [
            'subject' => 'Vi har tagit emot din bokningsförfrågan',
            'path' => 'booking/request-received-customer.php',
        ],
        'booking_approved_customer' => [
            'subject' => 'Din bokning är godkänd',
            'path' => 'booking/approved-customer.php',
        ],
        'booking_rejected_customer' => [
            'subject' => 'Din bokningsförfrågan kunde inte godkännas',
            'path' => 'booking/rejected-customer.php',
        ],
        'booking_cancelled_customer' => [
            'subject' => 'Din bokning är avbokad',
            'path' => 'booking/cancelled-customer.php',
        ],
        'new_booking_request_admin' => [
            'subject' => 'Ny bokningsförfrågan',
            'path' => 'booking/new-request-admin.php',
        ],
    ];

    public function __construct(private readonly ?string $basePath = null)
    {
    }

    /**
     * Return the localized email subject for a template.
     */
    public function subject(string $templateKey): string
    {
        $template = $this->template($templateKey);

        return $template['subject'];
    }

    /**
     * Render HTML and text bodies without storing them in the database.
     *
     * @param array<string, mixed> $context
     * @return array{html: string, text: string}
     */
    public function render(string $templateKey, array $context): array
    {
        $template = $this->template($templateKey);
        $templatePath = $this->templatePath($template['path']);
        $safeContext = $this->safeContext($context);
        $html = $this->renderFile($templatePath, $safeContext);

        return [
            'html' => $html,
            'text' => $this->htmlToText($html),
        ];
    }

    /**
     * @return array{subject: string, path: string}
     */
    private function template(string $templateKey): array
    {
        $normalized = trim($templateKey);

        if (!array_key_exists($normalized, self::TEMPLATES)) {
            throw new NotificationException('Email template is not available.');
        }

        return self::TEMPLATES[$normalized];
    }

    private function templatePath(string $relativePath): string
    {
        $basePath = $this->basePath ?? dirname(__DIR__, 2);
        $path = $basePath . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views'
            . DIRECTORY_SEPARATOR . 'emails' . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        if (!is_file($path)) {
            throw new NotificationException('Email template file is missing.');
        }

        return $path;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function renderFile(string $templatePath, array $context): string
    {
        $e = static function (mixed $value): string {
            return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };
        $money = static function (mixed $value): string {
            if ($value === null || $value === '') {
                return '';
            }

            return number_format((float) $value, 2, ',', ' ') . ' kr';
        };

        ob_start();
        require $templatePath;
        $html = ob_get_clean();

        if ($html === false || trim($html) === '') {
            throw new NotificationException('Email template rendered empty output.');
        }

        return $html;
    }

    private function htmlToText(string $html): string
    {
        $withBreaks = preg_replace('/<(br|\/p|\/h1|\/h2|\/li)>/i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function safeContext(array $context): array
    {
        unset($context['internal_note'], $context['audit_data'], $context['auth_data']);

        return $context;
    }
}
