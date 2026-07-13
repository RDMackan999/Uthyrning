<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Renders simple PHP templates from resources/views.
 */
final class View
{
    private readonly string $viewsPath;

    public function __construct(?string $viewsPath = null)
    {
        $this->viewsPath = rtrim(
            $viewsPath ?? dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views',
            DIRECTORY_SEPARATOR,
        );
    }

    /**
     * Render a template and return its HTML.
     *
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = []): string
    {
        $path = $this->resolvePath($template);
        $safeData = $this->safeData($data);

        return $this->renderFile($path, $safeData);
    }

    /**
     * Render a template inside a layout.
     *
     * @param array<string, mixed> $data
     */
    public function renderWithLayout(string $template, string $layout, array $data = []): string
    {
        $content = $this->render($template, $data);

        return $this->render($layout, array_merge($data, [
            'content' => $content,
        ]));
    }

    /**
     * Resolve a template name such as pages/home to an allowed PHP file.
     */
    private function resolvePath(string $template): string
    {
        $template = trim(str_replace('\\', '/', $template), '/');

        if ($template === '' || str_contains($template, '..')) {
            throw new RuntimeException(sprintf('Invalid view template: %s', $template));
        }

        $basePath = realpath($this->viewsPath);
        $path = realpath(
            $this->viewsPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $template) . '.php',
        );

        if ($basePath === false || $path === false || !is_file($path)) {
            throw new RuntimeException(sprintf('View not found: %s', $template));
        }

        if (!str_starts_with($path, $basePath . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException(sprintf('View path is not allowed: %s', $template));
        }

        return $path;
    }

    /**
     * Keep only keys that can safely become local PHP variables.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function safeData(array $data): array
    {
        $safeData = [];

        foreach ($data as $key => $value) {
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
                continue;
            }

            if (str_starts_with($key, '__')) {
                continue;
            }

            $safeData[$key] = $value;
        }

        return $safeData;
    }

    /**
     * Render a PHP file inside an isolated closure.
     *
     * @param array<string, mixed> $__data
     */
    private function renderFile(string $__path, array $__data): string
    {
        return (static function () use ($__path, $__data): string {
            extract($__data, EXTR_SKIP);

            ob_start();
            require $__path;
            $content = ob_get_clean();

            return $content === false ? '' : $content;
        })();
    }
}
