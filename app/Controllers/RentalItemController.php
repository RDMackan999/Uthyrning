<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\CsrfTokenManager;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Http\RentalItemFormRequest;
use App\Models\RentalItem;
use App\Repositories\CategoryRepository;
use App\Repositories\OrganizationRepository;
use App\Repositories\RentalItemRepository;
use Throwable;

/**
 * Handles the first protected admin CRUD flow for rental items.
 */
final class RentalItemController extends BaseController
{
    private readonly CsrfTokenManager $csrfTokenManager;

    public function __construct(
        private readonly RentalItemRepository $rentalItemRepository = new RentalItemRepository(),
        private readonly OrganizationRepository $organizationRepository = new OrganizationRepository(),
        private readonly CategoryRepository $categoryRepository = new CategoryRepository(),
        private readonly RentalItemFormRequest $formRequest = new RentalItemFormRequest(),
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
        return new self(new RentalItemRepository(), new OrganizationRepository(), new CategoryRepository());
    }

    /**
     * Show all non-deleted rental items for administration.
     */
    public function index(Request $request): Response
    {
        return $this->viewWithLayout('admin/items/index', 'layouts/admin', [
            'pageTitle' => 'Objekt',
            'items' => $this->rentalItemRepository->findAllForAdmin()->toArray(),
            'csrfToken' => $this->csrfTokenManager->generateToken($request),
            'message' => $request->query('archived') === '1' ? 'Objektet har arkiverats.' : null,
        ]);
    }

    /**
     * Show create form.
     */
    public function create(Request $request): Response
    {
        return $this->renderCreate($request, $this->defaultFormData());
    }

    /**
     * Store a new rental item.
     */
    public function store(Request $request): Response
    {
        $postData = $this->postData($request);

        if (!$this->csrfTokenManager->validate($request, $this->stringValue($postData['csrf_token'] ?? null))) {
            return $this->renderCreate($request, $postData, [
                'form' => 'Formuläret kunde inte verifieras. Försök igen.',
            ]);
        }

        $validated = $this->formRequest->validate($postData);

        if ($validated['errors'] !== []) {
            return $this->renderCreate($request, $validated['data'], $validated['errors']);
        }

        try {
            $item = $this->rentalItemRepository->create($validated['data']);
        } catch (Throwable) {
            return $this->renderCreate($request, $validated['data'], [
                'form' => 'Objektet kunde inte sparas. Kontrollera uppgifterna och försök igen.',
            ]);
        }

        return $this->redirect('/admin/items/' . rawurlencode((string) $item->toArray()['public_id']) . '/edit?saved=1');
    }

    /**
     * Show edit form.
     */
    public function edit(Request $request): Response
    {
        $item = $this->itemFromRoute($request);

        return $this->renderEdit($request, $item, $item->toArray(), [], $request->query('saved') === '1'
            ? 'Objektet har sparats.'
            : null);
    }

    /**
     * Update or archive an existing rental item.
     */
    public function update(Request $request): Response
    {
        $item = $this->itemFromRoute($request);
        $postData = $this->postData($request);

        if (!$this->csrfTokenManager->validate($request, $this->stringValue($postData['csrf_token'] ?? null))) {
            return $this->renderEdit($request, $item, $postData, [
                'form' => 'Formuläret kunde inte verifieras. Försök igen.',
            ]);
        }

        if ($this->stringValue($postData['_action'] ?? null) === 'archive') {
            $this->rentalItemRepository->delete((int) $item->toArray()['id']);

            return $this->redirect('/admin/items?archived=1');
        }

        $validated = $this->formRequest->validate($postData, $item);

        if ($validated['errors'] !== []) {
            return $this->renderEdit($request, $item, $validated['data'], $validated['errors']);
        }

        try {
            $updated = $this->rentalItemRepository->update((int) $item->toArray()['id'], $validated['data']);
        } catch (Throwable) {
            return $this->renderEdit($request, $item, $validated['data'], [
                'form' => 'Objektet kunde inte sparas. Kontrollera uppgifterna och försök igen.',
            ]);
        }

        return $this->redirect('/admin/items/' . rawurlencode((string) $updated->toArray()['public_id']) . '/edit?saved=1');
    }

    /**
     * Render create view with selectable organizations and scoped categories.
     *
     * @param array<string, mixed> $data
     * @param array<string, string> $errors
     */
    private function renderCreate(Request $request, array $data, array $errors = []): Response
    {
        return $this->viewWithLayout('admin/items/create', 'layouts/admin', $this->formViewData($request, $data, $errors) + [
            'pageTitle' => 'Nytt objekt',
        ]);
    }

    /**
     * Render edit view with existing immutable identifiers.
     *
     * @param array<string, mixed> $data
     * @param array<string, string> $errors
     */
    private function renderEdit(
        Request $request,
        RentalItem $item,
        array $data,
        array $errors = [],
        ?string $message = null
    ): Response {
        return $this->viewWithLayout('admin/items/edit', 'layouts/admin', $this->formViewData($request, $data, $errors) + [
            'pageTitle' => 'Redigera objekt',
            'item' => $item->toArray(),
            'message' => $message,
        ]);
    }

    /**
     * Build shared form data for create and edit views.
     *
     * @param array<string, mixed> $data
     * @param array<string, string> $errors
     * @return array<string, mixed>
     */
    private function formViewData(Request $request, array $data, array $errors): array
    {
        $organizations = $this->organizationRepository->findAllActive()->toArray();
        $selectedOrganizationId = $this->selectedOrganizationId($data, $organizations);

        if ($selectedOrganizationId !== null && !is_numeric($data['organization_id'] ?? null)) {
            $data['organization_id'] = $selectedOrganizationId;
        }

        return [
            'data' => $data,
            'errors' => $errors,
            'organizations' => $organizations,
            'categories' => $selectedOrganizationId === null
                ? $this->categoryRepository->findGlobal()->toArray()
                : $this->categoryRepository->findForOrganization($selectedOrganizationId)->toArray(),
            'csrfToken' => $this->csrfTokenManager->generateToken($request),
        ];
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
     * @param array<string, mixed> $data
     * @param list<array<string, mixed>> $organizations
     */
    private function selectedOrganizationId(array $data, array $organizations): ?int
    {
        $organizationId = $data['organization_id'] ?? null;

        if (is_numeric($organizationId)) {
            return (int) $organizationId;
        }

        $firstOrganization = $organizations[0]['id'] ?? null;

        return is_numeric($firstOrganization) ? (int) $firstOrganization : null;
    }

    /**
     * Default values for the initial create form.
     *
     * @return array<string, mixed>
     */
    private function defaultFormData(): array
    {
        return [
            'name' => '',
            'short_name' => '',
            'slug' => '',
            'description' => '',
            'organization_id' => null,
            'primary_category_id' => null,
            'is_active' => true,
            'is_rentable' => false,
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
}
