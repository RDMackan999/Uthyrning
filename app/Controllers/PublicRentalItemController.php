<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\NotFoundException;
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

    /**
     * Show a public detail page for one published rental item.
     */
    public function show(Request $request): Response
    {
        $item = $this->rentalItemRepository->findPublicDetail(
            (string) $request->route('public_id', ''),
            (string) $request->route('slug', '')
        );

        if ($item === null) {
            throw new NotFoundException();
        }

        $itemData = $item->toArray();

        return $this->viewWithLayout('public/items/show', 'layouts/public', [
            'pageTitle' => (string) ($itemData['name'] ?? 'Objekt'),
            'item' => $itemData,
        ]);
    }
}
