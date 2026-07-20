<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\CsrfTokenManager;
use App\Core\ModelException;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Http\ItemRateFormRequest;
use App\Models\ItemRate;
use App\Models\RentalItem;
use App\Repositories\ItemRateRepository;
use App\Repositories\RentalItemRepository;
use Throwable;

/**
 * Handles protected admin CRUD flow for rental item rates.
 */
final class ItemRateController extends BaseController
{
    private readonly CsrfTokenManager $csrfTokenManager;

    public function __construct(
        private readonly RentalItemRepository $rentalItemRepository = new RentalItemRepository(),
        private readonly ItemRateRepository $itemRateRepository = new ItemRateRepository(),
        private readonly ItemRateFormRequest $formRequest = new ItemRateFormRequest(),
        ?CsrfTokenManager $csrfTokenManager = null,
    ) {
        parent::__construct();

        $this->csrfTokenManager = $csrfTokenManager ?? CsrfTokenManager::fromConfig();
    }

    /**
     * Create controller with configured CSRF storage.
     */
    public static function fromConfig(): self
    {
        $itemRateRepository = new ItemRateRepository();

        return new self(
            new RentalItemRepository(),
            $itemRateRepository,
            new ItemRateFormRequest($itemRateRepository)
        );
    }

    /**
     * Show non-deleted rates for one rental item.
     */
    public function index(Request $request): Response
    {
        $item = $this->itemFromRoute($request);
        $itemData = $item->toArray();
        $organizationId = (int) ($itemData['organization_id'] ?? 0);
        $rentalItemId = (int) ($itemData['id'] ?? 0);

        return $this->viewWithLayout('admin/item-rates/index', 'layouts/admin', [
            'pageTitle' => 'Priser',
            'item' => $itemData,
            'rates' => $this->itemRateRepository->findForItem($organizationId, $rentalItemId)->toArray(),
            'csrfToken' => $this->csrfTokenManager->generateToken($request),
            'message' => $this->savedMessage($request),
        ]);
    }

    /**
     * Show create form.
     */
    public function create(Request $request): Response
    {
        $item = $this->itemFromRoute($request);

        return $this->renderCreate($request, $item, $this->defaultFormData());
    }

    /**
     * Store a new rental item rate.
     */
    public function store(Request $request): Response
    {
        $item = $this->itemFromRoute($request);
        $postData = $this->postData($request);

        if (!$this->csrfTokenManager->validate($request, $this->stringValue($postData['csrf_token'] ?? null))) {
            return $this->renderCreate($request, $item, $postData, [
                'form' => 'Formuläret kunde inte verifieras. Försök igen.',
            ]);
        }

        $itemData = $item->toArray();
        $organizationId = (int) ($itemData['organization_id'] ?? 0);
        $rentalItemId = (int) ($itemData['id'] ?? 0);
        $validated = $this->formRequest->validate($postData, $organizationId, $rentalItemId);

        if ($validated['errors'] !== []) {
            return $this->renderCreate($request, $item, $validated['data'], $validated['errors']);
        }

        try {
            $this->itemRateRepository->create($validated['data'] + [
                'organization_id' => $organizationId,
                'rental_item_id' => $rentalItemId,
            ]);
        } catch (Throwable) {
            return $this->renderCreate($request, $item, $validated['data'], [
                'form' => 'Priset kunde inte sparas. Kontrollera uppgifterna och försök igen.',
            ]);
        }

        return $this->redirect($this->ratesPath($item) . '?saved=created');
    }

    /**
     * Show edit form.
     */
    public function edit(Request $request): Response
    {
        $item = $this->itemFromRoute($request);
        $rate = $this->rateFromRoute($request, $item);

        return $this->renderEdit($request, $item, $rate, $rate->toArray());
    }

    /**
     * Update an existing rental item rate.
     */
    public function update(Request $request): Response
    {
        $item = $this->itemFromRoute($request);
        $rate = $this->rateFromRoute($request, $item);
        $postData = $this->postData($request);

        if (!$this->csrfTokenManager->validate($request, $this->stringValue($postData['csrf_token'] ?? null))) {
            return $this->renderEdit($request, $item, $rate, $postData, [
                'form' => 'Formuläret kunde inte verifieras. Försök igen.',
            ]);
        }

        $itemData = $item->toArray();
        $organizationId = (int) ($itemData['organization_id'] ?? 0);
        $rentalItemId = (int) ($itemData['id'] ?? 0);
        $validated = $this->formRequest->validate($postData, $organizationId, $rentalItemId, $rate);

        if ($validated['errors'] !== []) {
            return $this->renderEdit($request, $item, $rate, $validated['data'], $validated['errors']);
        }

        try {
            $this->itemRateRepository->update((int) ($rate->toArray()['id'] ?? 0), $validated['data'], $organizationId);
        } catch (Throwable) {
            return $this->renderEdit($request, $item, $rate, $validated['data'], [
                'form' => 'Priset kunde inte sparas. Kontrollera uppgifterna och försök igen.',
            ]);
        }

        return $this->redirect($this->ratesPath($item) . '?saved=updated');
    }

    /**
     * Soft delete an existing rental item rate.
     */
    public function archive(Request $request): Response
    {
        $item = $this->itemFromRoute($request);
        $rate = $this->rateFromRoute($request, $item);
        $postData = $this->postData($request);

        if (!$this->csrfTokenManager->validate($request, $this->stringValue($postData['csrf_token'] ?? null))) {
            return $this->redirect($this->ratesPath($item) . '?saved=invalid');
        }

        $this->itemRateRepository->delete(
            (int) ($rate->toArray()['id'] ?? 0),
            (int) ($item->toArray()['organization_id'] ?? 0)
        );

        return $this->redirect($this->ratesPath($item) . '?saved=archived');
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $errors
     */
    private function renderCreate(Request $request, RentalItem $item, array $data, array $errors = []): Response
    {
        return $this->viewWithLayout('admin/item-rates/create', 'layouts/admin', [
            'pageTitle' => 'Nytt pris',
            'item' => $item->toArray(),
            'data' => $data,
            'errors' => $errors,
            'rateTypes' => ItemRateFormRequest::rateTypes(),
            'csrfToken' => $this->csrfTokenManager->generateToken($request),
        ]);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $errors
     */
    private function renderEdit(
        Request $request,
        RentalItem $item,
        ItemRate $rate,
        array $data,
        array $errors = []
    ): Response {
        return $this->viewWithLayout('admin/item-rates/edit', 'layouts/admin', [
            'pageTitle' => 'Redigera pris',
            'item' => $item->toArray(),
            'rate' => $rate->toArray(),
            'data' => $data,
            'errors' => $errors,
            'rateTypes' => ItemRateFormRequest::rateTypes(),
            'csrfToken' => $this->csrfTokenManager->generateToken($request),
        ]);
    }

    /**
     * Resolve route public_id to a non-deleted rental item.
     */
    private function itemFromRoute(Request $request): RentalItem
    {
        $publicId = $this->stringValue($request->route('public_id'));

        if ($publicId === '') {
            throw new NotFoundException();
        }

        $item = $this->rentalItemRepository->findByPublicId($publicId);

        if ($item === null) {
            throw new NotFoundException();
        }

        return $item;
    }

    /**
     * Resolve route id to a non-deleted rate scoped to the current item.
     */
    private function rateFromRoute(Request $request, RentalItem $item): ItemRate
    {
        $rateId = $this->stringValue($request->route('id'));

        if ($rateId === '' || !ctype_digit($rateId)) {
            throw new NotFoundException();
        }

        $itemData = $item->toArray();

        try {
            return $this->itemRateRepository->findByIdForItem(
                (int) ($itemData['organization_id'] ?? 0),
                (int) ($itemData['id'] ?? 0),
                (int) $rateId
            );
        } catch (ModelException) {
            throw new NotFoundException();
        }
    }

    /**
     * Default values for the initial create form.
     *
     * @return array<string, mixed>
     */
    private function defaultFormData(): array
    {
        return [
            'rate_type' => 'daily',
            'amount' => '',
            'currency' => 'SEK',
            'is_active' => true,
        ];
    }

    /**
     * Read POST data as a safe array shape.
     *
     * @return array<string, mixed>
     */
    private function postData(Request $request): array
    {
        $postData = $request->post();

        return is_array($postData) ? $postData : [];
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) || is_numeric($value) ? trim((string) $value) : '';
    }

    private function ratesPath(RentalItem $item): string
    {
        return '/admin/items/' . rawurlencode((string) ($item->toArray()['public_id'] ?? '')) . '/rates';
    }

    private function savedMessage(Request $request): ?string
    {
        return match ($request->query('saved')) {
            'created' => 'Priset har skapats.',
            'updated' => 'Priset har sparats.',
            'archived' => 'Priset har arkiverats.',
            'invalid' => 'Formuläret kunde inte verifieras. Försök igen.',
            default => null,
        };
    }
}
