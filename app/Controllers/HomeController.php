<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Response;

/**
 * Routes the PHP home entry to the public catalogue.
 */
final class HomeController extends BaseController
{
    /**
     * Send visitors to the first usable public rental flow.
     */
    public function index(): Response
    {
        return $this->redirect('/items');
    }
}
