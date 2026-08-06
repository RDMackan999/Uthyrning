<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\RentalItemRepository;

/**
 * Handles public rental item listing without authentication.
 */
final class PublicRentalItemController extends BaseController
{
    public function __construct(
        private readonly RentalItemRepository $rentalItemRepository = new RentalItemRepository(),
    ) {
        parent::__construct();
    }

    /**
     * Show the first public list of published rental items.
     */
    public function index(Request $request): Response
    {
        return $this->viewWithLayout('public/items/index', 'layouts/public', [
            'pageTitle' => 'Hyr objekt',
            'items' => $this->rentalItemRepository->findPublicListing()->toArray(),
        ]);
    }
}
