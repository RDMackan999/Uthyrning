<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\CategoryRepository;
use App\Repositories\RentalItemRepository;

/**
 * Handles public rental item listing without authentication.
 */
final class PublicRentalItemController extends BaseController
{
    public function __construct(
        private readonly RentalItemRepository $rentalItemRepository = new RentalItemRepository(),
        private readonly CategoryRepository $categoryRepository = new CategoryRepository(),
    ) {
        parent::__construct();
    }

    /**
     * Show the first public list of published rental items.
     */
    public function index(Request $request): Response
    {
        $filters = [
            'q' => $this->normalizeSearchQuery($request->query('q', '')),
            'category' => $this->normalizeCategorySlug($request->query('category', '')),
        ];
        $items = $this->rentalItemRepository->findPublicListing($filters)->toArray();

        return $this->viewWithLayout('public/items/index', 'layouts/public', [
            'pageTitle' => 'Hyr objekt',
            'items' => $items,
            'categories' => $this->categoryRepository->findPublicFilterOptions()->toArray(),
            'filters' => $filters,
            'hasActiveFilters' => $filters['q'] !== '' || $filters['category'] !== '',
            'resultCount' => count($items),
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

    private function normalizeSearchQuery(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        $query = trim((string) $value);

        if ($query === '') {
            return '';
        }

        return function_exists('mb_substr') ? mb_substr($query, 0, 100) : substr($query, 0, 100);
    }

    private function normalizeCategorySlug(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        return strtolower(trim((string) $value));
    }
}
