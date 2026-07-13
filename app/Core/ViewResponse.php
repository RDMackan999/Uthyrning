<?php

declare(strict_types=1);

namespace App\Core;

/**
 * HTTP response for rendered PHP views.
 */
final class ViewResponse extends Response
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        string $template,
        array $data = [],
        int $statusCode = 200,
        ?View $view = null,
        ?string $layout = null
    ) {
        $renderer = $view ?? new View();
        $content = $layout === null
            ? $renderer->render($template, $data)
            : $renderer->renderWithLayout($template, $layout, $data);

        parent::__construct(
            $content,
            $statusCode,
            ['Content-Type' => 'text/html; charset=utf-8'],
        );
    }
}
