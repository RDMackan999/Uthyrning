<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\CsrfTokenManager;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Http\CustomerFormRequest;
use App\Models\Customer;
use App\Repositories\CompanyRepository;
use App\Repositories\CustomerRepository;
use App\Services\AuditService;
use Throwable;

/**
 * Handles protected customer administration for Version 1.
 */
final class AdminCustomerController extends BaseController
{
    /**
     * @var list<string>
     */
    private const STATUS_KEYS = ['active', 'inactive', 'blocked'];

    private readonly CsrfTokenManager $csrfTokenManager;

    public function __construct(
        private readonly CustomerRepository $customerRepository = new CustomerRepository(),
        private readonly CompanyRepository $companyRepository = new CompanyRepository(),
        private readonly CustomerFormRequest $formRequest = new CustomerFormRequest(),
        private readonly AuditService $auditService = new AuditService(),
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
        $customerRepository = new CustomerRepository();

        return new self(
            $customerRepository,
            new CompanyRepository(),
            new CustomerFormRequest($customerRepository),
            new AuditService()
        );
    }

    /**
     * Show non-deleted customers for administration.
     */
    public function index(Request $request): Response
    {
        $statusFilter = $this->statusFilter($request->query('status'));
        $query = $this->searchQuery($request->query('q'));

        return $this->viewWithLayout('admin/customers/index', 'layouts/admin', [
            'pageTitle' => 'Kunder',
            'customers' => $this->customerRepository->findAllForAdmin($statusFilter, $query)->toArray(),
            'statusFilter' => $statusFilter,
            'query' => $query,
            'statusOptions' => $this->statusOptions(),
            'csrfToken' => $this->csrfTokenManager->generateToken($request),
            'message' => $this->message($request),
            'error' => $this->error($request),
        ]);
    }

    /**
     * Show one customer with booking history.
     */
    public function show(Request $request): Response
    {
        $customer = $this->customerFromRoute($request);
        $customerData = $customer->toArray();
        $organizationId = (int) ($customerData['organization_id'] ?? 0);
        $customerId = (int) ($customerData['id'] ?? 0);

        return $this->viewWithLayout('admin/customers/show', 'layouts/admin', [
            'pageTitle' => 'Kunddetalj',
            'customer' => $customerData,
            'bookingHistory' => $this->customerRepository->findBookingHistory($customerId, $organizationId),
            'statusOptions' => $this->statusOptions(),
            'csrfToken' => $this->csrfTokenManager->generateToken($request),
            'message' => $this->message($request),
            'error' => $this->error($request),
        ]);
    }

    /**
     * Show edit form for editable customer fields.
     */
    public function edit(Request $request): Response
    {
        $customer = $this->customerFromRoute($request);

        return $this->renderEdit($request, $customer, $customer->toArray(), [], $this->message($request));
    }

    /**
     * Persist editable customer fields.
     */
    public function update(Request $request): Response
    {
        $customer = $this->customerFromRoute($request);
        $postData = $this->postData($request);

        if (!$this->csrfTokenManager->validate($request, $this->stringValue($postData['csrf_token'] ?? null))) {
            return $this->renderEdit($request, $customer, $postData, [
                'form' => 'Formuläret kunde inte verifieras. Försök igen.',
            ]);
        }

        $validated = $this->formRequest->validate($postData, $customer);

        if ($validated['errors'] !== []) {
            return $this->renderEdit($request, $customer, $validated['data'], $validated['errors']);
        }

        try {
            $updated = $this->customerRepository->update((int) ($customer->toArray()['id'] ?? 0), $validated['data']);
        } catch (Throwable) {
            return $this->renderEdit($request, $customer, $validated['data'], [
                'form' => 'Kunden kunde inte sparas. Kontrollera uppgifterna och försök igen.',
            ]);
        }

        $updatedData = $updated->toArray();
        $this->auditService->record(
            'customer_updated',
            $request->authenticatedUserId(),
            'customer',
            (int) ($updatedData['id'] ?? 0),
            $request->ipAddress(),
            $request->userAgent(),
            [
                'organization_id' => (int) ($updatedData['organization_id'] ?? 0),
                'customer_type_key' => (string) ($updatedData['customer_type_key'] ?? ''),
                'result' => 'updated',
            ]
        );

        return $this->redirect($this->customerPath($updated) . '/edit?saved=1');
    }

    /**
     * Change customer lifecycle status.
     */
    public function updateStatus(Request $request): Response
    {
        $customer = $this->customerFromRoute($request);
        $postData = $this->postData($request);
        $path = $this->customerPath($customer);

        if (!$this->csrfTokenManager->validate($request, $this->stringValue($postData['csrf_token'] ?? null))) {
            return $this->redirect($path . '?error=csrf');
        }

        $validated = $this->formRequest->validateStatus($postData);

        if ($validated['errors'] !== [] || $validated['status_key'] === null) {
            return $this->redirect($path . '?error=status');
        }

        $beforeStatus = (string) ($customer->toArray()['status_key'] ?? '');

        try {
            $updated = $this->customerRepository->updateStatus(
                (int) ($customer->toArray()['id'] ?? 0),
                $validated['status_key']
            );
        } catch (Throwable) {
            return $this->redirect($path . '?error=status');
        }

        $updatedData = $updated->toArray();
        $this->auditService->record(
            'customer_status_changed',
            $request->authenticatedUserId(),
            'customer',
            (int) ($updatedData['id'] ?? 0),
            $request->ipAddress(),
            $request->userAgent(),
            [
                'organization_id' => (int) ($updatedData['organization_id'] ?? 0),
                'from_status_key' => $beforeStatus,
                'to_status_key' => (string) ($updatedData['status_key'] ?? ''),
                'result' => 'updated',
            ]
        );

        return $this->redirect($this->customerPath($updated) . '?message=status');
    }

    /**
     * Render edit view with immutable customer identifiers.
     *
     * @param array<string, mixed> $data
     * @param array<string, string> $errors
     */
    private function renderEdit(
        Request $request,
        Customer $customer,
        array $data,
        array $errors = [],
        ?string $message = null
    ): Response {
        $customerData = $customer->toArray();
        $organizationId = (int) ($customerData['organization_id'] ?? 0);

        return $this->viewWithLayout('admin/customers/edit', 'layouts/admin', [
            'pageTitle' => 'Redigera kund',
            'customer' => $customerData,
            'data' => $data,
            'errors' => $errors,
            'companies' => $this->companyRepository->findActiveForOrganization($organizationId)->toArray(),
            'csrfToken' => $this->csrfTokenManager->generateToken($request),
            'message' => $message,
        ]);
    }

    /**
     * Resolve route id to a non-deleted customer.
     */
    private function customerFromRoute(Request $request): Customer
    {
        $id = $this->stringValue($request->route('id'));

        if ($id === '' || !ctype_digit($id)) {
            throw new NotFoundException();
        }

        try {
            return $this->customerRepository->findById((int) $id);
        } catch (Throwable) {
            throw new NotFoundException();
        }
    }

    private function customerPath(Customer $customer): string
    {
        return '/admin/customers/' . rawurlencode((string) ($customer->toArray()['id'] ?? ''));
    }

    /**
     * @return array<string, string>
     */
    private function statusOptions(): array
    {
        return [
            'active' => 'Aktiv',
            'inactive' => 'Inaktiv',
            'blocked' => 'Spärrad',
        ];
    }

    private function statusFilter(mixed $value): ?string
    {
        $statusKey = strtolower($this->stringValue($value));

        return in_array($statusKey, self::STATUS_KEYS, true) ? $statusKey : null;
    }

    private function searchQuery(mixed $value): ?string
    {
        $query = $this->stringValue($value);

        return $query === '' ? null : substr($query, 0, 120);
    }

    /**
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

    private function message(Request $request): ?string
    {
        return match ($request->query('message') ?? $request->query('saved')) {
            '1' => 'Kunden har sparats.',
            'status' => 'Kundstatus har uppdaterats.',
            default => null,
        };
    }

    private function error(Request $request): ?string
    {
        return match ($request->query('error')) {
            'csrf' => 'Formuläret kunde inte verifieras. Försök igen.',
            'status' => 'Kundstatus kunde inte uppdateras.',
            default => null,
        };
    }
}
