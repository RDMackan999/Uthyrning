<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\CsrfTokenManager;
use App\Core\ModelException;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Models\ItemAvailabilityBlock;
use App\Models\RentalItem;
use App\Repositories\ItemAvailabilityBlockRepository;
use App\Repositories\RentalItemRepository;
use App\Services\AuditService;
use App\Services\BookingAvailabilityService;
use App\Services\OrganizationAuthorizationService;
use DateTimeImmutable;
use Throwable;

/**
 * Handles protected admin flow for manual item availability blocks.
 */
final class AdminAvailabilityBlockController extends BaseController
{
    private readonly CsrfTokenManager $csrfTokenManager;

    public function __construct(
        private readonly RentalItemRepository $rentalItemRepository = new RentalItemRepository(),
        private readonly ItemAvailabilityBlockRepository $availabilityBlockRepository = new ItemAvailabilityBlockRepository(),
        private readonly BookingAvailabilityService $availabilityService = new BookingAvailabilityService(),
        private readonly AuditService $auditService = new AuditService(),
        private readonly OrganizationAuthorizationService $authorizationService = new OrganizationAuthorizationService(),
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
        $availabilityBlockRepository = new ItemAvailabilityBlockRepository();

        return new self(
            new RentalItemRepository(),
            $availabilityBlockRepository,
            new BookingAvailabilityService(availabilityBlockRepository: $availabilityBlockRepository),
            new AuditService(),
            new OrganizationAuthorizationService()
        );
    }

    /**
     * Show manual availability blocks for one rental item.
     */
    public function index(Request $request): Response
    {
        $item = $this->itemFromRoute($request);
        $itemData = $item->toArray();
        $organizationId = (int) ($itemData['organization_id'] ?? 0);
        $rentalItemId = (int) ($itemData['id'] ?? 0);

        return $this->viewWithLayout('admin/availability-blocks/index', 'layouts/admin', [
            'pageTitle' => 'Kalenderblockeringar',
            'item' => $itemData,
            'blocks' => $this->availabilityBlockRepository->findForItem($organizationId, $rentalItemId)->toArray(),
            'reasonOptions' => ItemAvailabilityBlockRepository::reasonOptions(),
            'csrfToken' => $this->csrfTokenManager->generateToken($request),
            'message' => $this->message($request),
        ]);
    }

    /**
     * Show create form.
     */
    public function create(Request $request): Response
    {
        return $this->renderCreate($request, $this->itemFromRoute($request), $this->defaultFormData());
    }

    /**
     * Store a new manual availability block.
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
        $validated = $this->validate($postData, $organizationId, $rentalItemId);

        if ($validated['errors'] !== []) {
            return $this->renderCreate($request, $item, $validated['data'], $validated['errors']);
        }

        try {
            $block = $this->availabilityBlockRepository->create($validated['data'] + [
                'organization_id' => $organizationId,
                'rental_item_id' => $rentalItemId,
                'created_by_user_id' => $request->authenticatedUserId(),
            ]);
            $blockData = $block->toArray();

            $this->auditService->record(
                'availability_block_created',
                $request->authenticatedUserId(),
                'blocked_period',
                (int) ($blockData['id'] ?? 0),
                $request->ipAddress(),
                $request->userAgent(),
                [
                    'organization_id' => $organizationId,
                    'rental_item_id' => $rentalItemId,
                    'start_date' => $validated['data']['start_date'],
                    'end_date' => $validated['data']['end_date'],
                    'reason_code' => $validated['data']['reason_code'],
                ]
            );
        } catch (Throwable) {
            return $this->renderCreate($request, $item, $validated['data'], [
                'form' => 'Blockeringen kunde inte sparas. Kontrollera uppgifterna och försök igen.',
            ]);
        }

        return $this->redirect($this->blocksPath($item) . '?saved=created');
    }

    /**
     * Soft delete an existing manual availability block.
     */
    public function archive(Request $request): Response
    {
        $item = $this->itemFromRoute($request);
        $block = $this->blockFromRoute($request, $item);
        $postData = $this->postData($request);

        if (!$this->csrfTokenManager->validate($request, $this->stringValue($postData['csrf_token'] ?? null))) {
            return $this->redirect($this->blocksPath($item) . '?saved=invalid');
        }

        $blockData = $block->toArray();
        $itemData = $item->toArray();
        $organizationId = (int) ($itemData['organization_id'] ?? 0);
        $rentalItemId = (int) ($itemData['id'] ?? 0);
        $blockId = (int) ($blockData['id'] ?? 0);

        if ($this->availabilityBlockRepository->delete($blockId, $organizationId)) {
            $this->auditService->record(
                'availability_block_archived',
                $request->authenticatedUserId(),
                'blocked_period',
                $blockId,
                $request->ipAddress(),
                $request->userAgent(),
                [
                    'organization_id' => $organizationId,
                    'rental_item_id' => $rentalItemId,
                    'start_date' => $blockData['start_date'] ?? null,
                    'end_date' => $blockData['end_date'] ?? null,
                    'reason_code' => $blockData['reason_code'] ?? null,
                ]
            );
        }

        return $this->redirect($this->blocksPath($item) . '?saved=archived');
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $errors
     */
    private function renderCreate(Request $request, RentalItem $item, array $data, array $errors = []): Response
    {
        return $this->viewWithLayout('admin/availability-blocks/create', 'layouts/admin', [
            'pageTitle' => 'Ny kalenderblockering',
            'item' => $item->toArray(),
            'data' => $data,
            'errors' => $errors,
            'reasonOptions' => ItemAvailabilityBlockRepository::reasonOptions(),
            'csrfToken' => $this->csrfTokenManager->generateToken($request),
        ]);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{data: array<string, mixed>, errors: array<string, string>}
     */
    private function validate(array $input, int $organizationId, int $rentalItemId): array
    {
        $startDate = $this->stringValue($input['start_date'] ?? '');
        $endDate = $this->stringValue($input['end_date'] ?? '');
        $reasonCode = $this->stringValue($input['reason_code'] ?? 'manual');
        $internalNote = $this->nullableString($input['internal_note'] ?? null);
        $errors = [];

        if (!$this->isDate($startDate)) {
            $errors['start_date'] = 'Startdatum måste vara ett giltigt datum.';
        }

        if (!$this->isDate($endDate)) {
            $errors['end_date'] = 'Slutdatum måste vara ett giltigt datum.';
        }

        if ($this->isDate($startDate) && $this->isDate($endDate) && $startDate > $endDate) {
            $errors['end_date'] = 'Slutdatum måste vara samma dag eller efter startdatum.';
        }

        if (!array_key_exists($reasonCode, ItemAvailabilityBlockRepository::reasonOptions())) {
            $errors['reason_code'] = 'Välj en giltig blockeringstyp.';
        }

        if ($errors === []) {
            if ($this->availabilityService->hasBlockingBookings($organizationId, $rentalItemId, $startDate, $endDate)) {
                $errors['form'] = 'Perioden krockar med en befintlig blockerande bokning.';
            } elseif ($this->availabilityBlockRepository
                ->findBlockingForItemAndRange($organizationId, $rentalItemId, $startDate, $endDate, $reasonCode)
                ->count() > 0
            ) {
                $errors['form'] = 'Det finns redan en överlappande blockering av samma typ.';
            }
        }

        return [
            'data' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'reason_code' => $reasonCode,
                'internal_note' => $internalNote,
            ],
            'errors' => $errors,
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

        $itemData = $item->toArray();
        $this->authorizationService->assertCanAccessResource(
            $request,
            (int) ($itemData['organization_id'] ?? 0),
            'rental_item',
            (int) ($itemData['id'] ?? 0),
            'manage_availability'
        );

        return $item;
    }

    /**
     * Resolve route id to a non-deleted block scoped to the current item.
     */
    private function blockFromRoute(Request $request, RentalItem $item): ItemAvailabilityBlock
    {
        $blockId = $this->stringValue($request->route('id'));

        if ($blockId === '' || !ctype_digit($blockId)) {
            throw new NotFoundException();
        }

        $itemData = $item->toArray();

        try {
            $block = $this->availabilityBlockRepository->findByIdForItem(
                (int) ($itemData['organization_id'] ?? 0),
                (int) ($itemData['id'] ?? 0),
                (int) $blockId
            );
        } catch (ModelException) {
            throw new NotFoundException();
        }

        $this->authorizationService->assertCanAccessResource(
            $request,
            (int) ($itemData['organization_id'] ?? 0),
            'blocked_period',
            (int) ($block->toArray()['id'] ?? 0),
            'archive'
        );

        return $block;
    }

    /**
     * @return array<string, mixed>
     */
    private function postData(Request $request): array
    {
        $postData = $request->post();

        return is_array($postData) ? $postData : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultFormData(): array
    {
        return [
            'start_date' => '',
            'end_date' => '',
            'reason_code' => 'manual',
            'internal_note' => '',
        ];
    }

    private function isDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    private function nullableString(mixed $value): ?string
    {
        $text = $this->stringValue($value);

        return $text === '' ? null : substr($text, 0, 1000);
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) || is_numeric($value) ? trim((string) $value) : '';
    }

    private function blocksPath(RentalItem $item): string
    {
        return '/admin/items/' . rawurlencode((string) ($item->toArray()['public_id'] ?? '')) . '/availability';
    }

    private function message(Request $request): ?string
    {
        return match ($request->query('saved')) {
            'created' => 'Kalenderblockeringen har skapats.',
            'archived' => 'Kalenderblockeringen har arkiverats.',
            'invalid' => 'Formuläret kunde inte verifieras. Försök igen.',
            default => null,
        };
    }
}
