<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\ModelException;
use App\Core\Request;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Repositories\OrganizationRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use Throwable;

/**
 * Owns the business rules for assigning organization_admin safely.
 */
final class OrganizationAdminAssignmentService
{
    private const ROLE_KEY = OrganizationAuthorizationService::ORGANIZATION_ADMIN;

    public function __construct(
        private readonly UserRepository $userRepository = new UserRepository(),
        private readonly OrganizationRepository $organizationRepository = new OrganizationRepository(),
        private readonly RoleRepository $roleRepository = new RoleRepository(),
        private readonly OrganizationAuthorizationService $authorizationService = new OrganizationAuthorizationService(),
        private readonly AuditService $auditService = new AuditService(),
    ) {
    }

    /**
     * Assign organization_admin to one existing user for one active organization.
     *
     * Returns false when the exact assignment already exists.
     */
    public function assignOrganizationAdmin(Request $request, int $targetUserId, int $organizationId): bool
    {
        $this->assertSystemAdminActor($request);
        $user = $this->activeUser($targetUserId);
        $organization = $this->activeOrganization($organizationId);
        $role = $this->organizationAdminRole();
        $roleId = (int) ($role->toArray()['id'] ?? 0);
        $pdo = Database::pdo();
        $startedTransaction = !$pdo->inTransaction();

        try {
            if ($startedTransaction) {
                $pdo->beginTransaction();
            }

            $created = $this->roleRepository->assignToUserForOrganization(
                (int) ($user->toArray()['id'] ?? 0),
                $roleId,
                (int) ($organization->toArray()['id'] ?? 0)
            );

            if ($created) {
                $this->auditAssignment($request, 'organization_admin_assigned', $user, $organization);
            }

            if ($startedTransaction) {
                $pdo->commit();
            }

            return $created;
        } catch (Throwable $exception) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Revoke only the exact organization_admin assignment.
     */
    public function revokeOrganizationAdmin(Request $request, int $targetUserId, int $organizationId): bool
    {
        $this->assertSystemAdminActor($request);
        $user = $this->activeUser($targetUserId);
        $organization = $this->activeOrganization($organizationId);
        $role = $this->organizationAdminRole();
        $roleId = (int) ($role->toArray()['id'] ?? 0);

        if (!$this->roleRepository->assignmentExists($targetUserId, $roleId, $organizationId)) {
            throw new ModelException('Organization admin assignment not found.');
        }

        $pdo = Database::pdo();
        $startedTransaction = !$pdo->inTransaction();

        try {
            if ($startedTransaction) {
                $pdo->beginTransaction();
            }

            $revoked = $this->roleRepository->revokeFromUserForOrganization($targetUserId, $roleId, $organizationId);

            if ($revoked) {
                $this->auditAssignment($request, 'organization_admin_revoked', $user, $organization);
            }

            if ($startedTransaction) {
                $pdo->commit();
            }

            return $revoked;
        } catch (Throwable $exception) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listAssignmentsForOrganization(?int $organizationId = null): array
    {
        $role = $this->organizationAdminRole();

        return $this->roleRepository->listAssignmentsForRole(
            (int) ($role->toArray()['id'] ?? 0),
            $organizationId
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listOrganizationsForUser(int $userId): array
    {
        $role = $this->organizationAdminRole();

        return $this->roleRepository->listOrganizationsForUserRole(
            $userId,
            (int) ($role->toArray()['id'] ?? 0)
        );
    }

    private function assertSystemAdminActor(Request $request): void
    {
        if (!$this->authorizationService->isSystemAdmin($request)) {
            $this->auditService->record(
                'authorization_denied',
                $request->authenticatedUserId(),
                'organization_admin_assignment',
                null,
                $request->ipAddress(),
                $request->userAgent(),
                [
                    'action' => 'manage_organization_admin',
                    'reason_code' => 'system_admin_required',
                    'result' => 'denied',
                ]
            );

            throw new ModelException('Only system_admin can manage organization admin assignments.');
        }
    }

    private function activeUser(int $userId): User
    {
        if ($userId <= 0) {
            throw new ModelException('User is required.');
        }

        $user = $this->userRepository->findById($userId);
        $data = $user->toArray();

        if (($data['status_key'] ?? null) !== 'active') {
            throw new ModelException('User is not active.');
        }

        return $user;
    }

    private function activeOrganization(int $organizationId): Organization
    {
        if ($organizationId <= 0) {
            throw new ModelException('Organization is required.');
        }

        $organization = $this->organizationRepository->findById($organizationId);
        $data = $organization->toArray();

        if (($data['status_key'] ?? null) !== 'active') {
            throw new ModelException('Organization is not active.');
        }

        return $organization;
    }

    private function organizationAdminRole(): Role
    {
        $role = $this->roleRepository->findOrganizationAdminRole();

        if ($role === null) {
            throw new ModelException('Organization admin role is missing.');
        }

        $data = $role->toArray();

        if (($data['role_key'] ?? null) !== self::ROLE_KEY || ($data['organization_id'] ?? null) !== null) {
            throw new ModelException('Organization admin role is not configured as expected.');
        }

        return $role;
    }

    private function auditAssignment(Request $request, string $eventName, User $user, Organization $organization): void
    {
        $userData = $user->toArray();
        $organizationData = $organization->toArray();

        $this->auditService->record(
            $eventName,
            $request->authenticatedUserId(),
            'user_role',
            (int) ($userData['id'] ?? 0),
            $request->ipAddress(),
            $request->userAgent(),
            [
                'target_user_id' => (int) ($userData['id'] ?? 0),
                'organization_id' => (int) ($organizationData['id'] ?? 0),
                'role_key' => self::ROLE_KEY,
                'result' => 'changed',
            ]
        );
    }
}
