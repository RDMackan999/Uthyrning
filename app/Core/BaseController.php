<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Base class for future HTTP controllers.
 *
 * Sprint 1F intentionally contains no authentication, middleware or business logic.
 */
abstract class BaseController
{
    public function __construct(private readonly ?View $viewRenderer = null)
    {
    }

    /**
     * Render a PHP view template as an HTML response.
     *
     * @param array<string, mixed> $data
     */
    protected function view(string $template, array $data = []): Response
    {
        $renderer = $this->viewRenderer ?? new View();

        return Response::html($renderer->render($template, $data));
    }

    /**
     * Return a JSON response.
     *
     * @param array<string, mixed> $data
     */
    protected function json(array $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    /**
     * Return an HTTP redirect response.
     */
    protected function redirect(string $url, int $status = 302): RedirectResponse
    {
        return new RedirectResponse($url, $status);
    }
}
