<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\CsrfTokenManager;
use App\Core\ModelException;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\OrganizationRepository;
use App\Repositories\UserRepository;
use App\Services\OrganizationAdminAssignmentService;
use Throwable;

/**
 * Minimal system-admin flow for organization_admin assignments.
 */
final class OrganizationAdminAssignmentController extends BaseController
{
    private readonly CsrfTokenManager $csrfTokenManager;

    public function __construct(
        private readonly OrganizationAdminAssignmentService $assignmentService = new OrganizationAdminAssignmentService(),
        private readonly UserRepository $userRepository = new UserRepository(),
        private readonly OrganizationRepository $organizationRepository = new OrganizationRepository(),
        ?CsrfTokenManager $csrfTokenManager = null,
    ) {
        parent::__construct();

        $this->csrfTokenManager = $csrfTokenManager ?? CsrfTokenManager::fromConfig();
    }

    public static function fromConfig(): self
    {
        return new self();
    }

    /**
     * Show current organization_admin assignments.
     */
    public function index(Request $request): Response
    {
        return $this->viewWithLayout('admin/organization-admins/index', 'layouts/admin', [
            'pageTitle' => 'Organisationsadministratörer',
            'assignments' => $this->assignmentService->listAssignmentsForOrganization(),
            'csrfToken' => $this->csrfTokenManager->generateToken($request),
            'message' => $this->message($request),
            'error' => $this->error($request),
            'showSystemAdminNavigation' => true,
        ]);
    }

    /**
     * Show the assignment form for existing users and active organizations.
     */
    public function assign(Request $request): Response
    {
        return $this->renderAssign($request, [
            'user_id' => '',
            'organization_id' => '',
            'q' => $this->searchQuery($request->query('q')),
        ]);
    }

    /**
     * Persist an organization_admin assignment.
     */
    public function store(Request $request): Response
    {
        $postData = $this->postData($request);

        if (!$this->csrfTokenManager->validate($request, $this->stringValue($postData['csrf_token'] ?? null))) {
            return $this->renderAssign($request, $postData, [
                'form' => 'Formuläret kunde inte verifieras. Försök igen.',
            ]);
        }

        $userId = $this->positiveInt($postData['user_id'] ?? null);
        $organizationId = $this->positiveInt($postData['organization_id'] ?? null);

        if ($userId === null || $organizationId === null) {
            return $this->renderAssign($request, $postData, [
                'form' => 'Välj en giltig användare och organisation.',
            ]);
        }

        try {
            $created = $this->assignmentService->assignOrganizationAdmin($request, $userId, $organizationId);
        } catch (ModelException) {
            return $this->renderAssign($request, $postData, [
                'form' => 'Tilldelningen kunde inte sparas. Kontrollera användare och organisation.',
            ]);
        } catch (Throwable) {
            return $this->renderAssign($request, $postData, [
                'form' => 'Tilldelningen kunde inte sparas just nu.',
            ]);
        }

        return $this->redirect('/admin/organization-admins?message=' . ($created ? 'assigned' : 'exists'));
    }

    /**
     * Revoke one exact organization_admin assignment.
     */
    public function revoke(Request $request): Response
    {
        $postData = $this->postData($request);

        if (!$this->csrfTokenManager->validate($request, $this->stringValue($postData['csrf_token'] ?? null))) {
            return $this->redirect('/admin/organization-admins?error=csrf');
        }

        $userId = $this->positiveInt($request->route('user_id'));
        $organizationId = $this->positiveInt($request->route('organization_id'));

        if ($userId === null || $organizationId === null) {
            return $this->redirect('/admin/organization-admins?error=invalid');
        }

        try {
            $this->assignmentService->revokeOrganizationAdmin($request, $userId, $organizationId);
        } catch (Throwable) {
            return $this->redirect('/admin/organization-admins?error=revoke');
        }

        return $this->redirect('/admin/organization-admins?message=revoked');
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $errors
     */
    private function renderAssign(Request $request, array $data, array $errors = []): Response
    {
        $query = $this->searchQuery($data['q'] ?? $request->query('q'));

        return $this->viewWithLayout('admin/organization-admins/assign', 'layouts/admin', [
            'pageTitle' => 'Tilldela organisationsadministratör',
            'data' => $data + ['q' => $query],
            'errors' => $errors,
            'users' => $this->userRepository->findAllActiveForAdmin($query),
            'organizations' => $this->organizationRepository->findAllActive()->toArray(),
            'csrfToken' => $this->csrfTokenManager->generateToken($request),
            'showSystemAdminNavigation' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function postData(Request $request): array
    {
        $postData = $request->post();

        return is_array($postData) ? $postData : [];
    }

    private function positiveInt(mixed $value): ?int
    {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }

        $text = trim((string) $value);

        return ctype_digit($text) && (int) $text > 0 ? (int) $text : null;
    }

    private function searchQuery(mixed $value): ?string
    {
        $query = $this->stringValue($value);

        return $query === '' ? null : substr($query, 0, 120);
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) || is_numeric($value) ? trim((string) $value) : '';
    }

    private function message(Request $request): ?string
    {
        return match ($request->query('message')) {
            'assigned' => 'Organisationsadministratören har tilldelats.',
            'exists' => 'Tilldelningen fanns redan.',
            'revoked' => 'Tilldelningen har återkallats.',
            default => null,
        };
    }

    private function error(Request $request): ?string
    {
        return match ($request->query('error')) {
            'csrf' => 'Formuläret kunde inte verifieras. Försök igen.',
            'invalid' => 'Ogiltig tilldelning.',
            'revoke' => 'Tilldelningen kunde inte återkallas.',
            default => null,
        };
    }
}
