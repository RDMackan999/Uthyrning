<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Response;

/**
 * Minimal backend home controller.
 */
final class HomeController extends BaseController
{
    /**
     * Show the backend initialized page.
     */
    public function index(): Response
    {
        return $this->view('pages/backend-home');
    }
}
