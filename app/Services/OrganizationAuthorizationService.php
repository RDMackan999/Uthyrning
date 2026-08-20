<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\AuthorizationContext;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Repositories\RoleRepository;

/**
 * Central authorization service for system and organization-scoped admin access.
 */
final class OrganizationAuthorizationService
{
    public const SYSTEM_ADMIN = 'system_admin';

    public const ORGANIZATION_ADMIN = 'organization_admin';

    public function __construct(
        private readonly RoleRepository $roleRepository = new RoleRepository(),
        private readonly AuditService $auditService = new AuditService(),
    ) {
    }

    /**
     * Build or return the trusted authorization context for this request.
     */
    public function contextForRequest(Request $request): ?AuthorizationContext
    {
        $existing = $request->authorizationContext();

        if ($existing !== null) {
            return $existing;
        }

        $userId = $request->authenticatedUserId();

        if ($userId === null) {
            return null;
        }

        $context = $this->contextForUser($userId);
        $request->setAuthorizationContext($context);

        return $context;
    }

    /**
     * Build authorization context from persisted role assignments only.
     */
    public function contextForUser(int $userId): AuthorizationContext
    {
        $organizationRolesByKey = [];

        foreach ($this->roleRepository->getOrganizationRolesForUser($userId) as $assignment) {
            $roleKey = $assignment['role_key'];
            $organizationId = $assignment['organization_id'];

            $organizationRolesByKey[$roleKey] ??= [];

            if (!in_array($organizationId, $organizationRolesByKey[$roleKey], true)) {
                $organizationRolesByKey[$roleKey][] = $organizationId;
            }
        }

        foreach ($organizationRolesByKey as $roleKey => $organizationIds) {
            sort($organizationIds);
            $organizationRolesByKey[$roleKey] = $organizationIds;
        }

        return new AuthorizationContext(
            $userId,
            $this->roleRepository->getSystemRolesForUser($userId),
            $organizationRolesByKey
        );
    }

    public function isSystemAdmin(Request|AuthorizationContext $requestOrContext): bool
    {
        $context = $requestOrContext instanceof Request
            ? $this->contextForRequest($requestOrContext)
            : $requestOrContext;

        return $context?->hasSystemRole(self::SYSTEM_ADMIN) ?? false;
    }

    public function isOrganizationAdminFor(Request|AuthorizationContext $requestOrContext, int $organizationId): bool
    {
        $context = $requestOrContext instanceof Request
            ? $this->contextForRequest($requestOrContext)
            : $requestOrContext;

        return $context?->hasOrganizationRole(self::ORGANIZATION_ADMIN, $organizationId) ?? false;
    }

    public function canAccessOrganization(Request|AuthorizationContext $requestOrContext, int $organizationId): bool
    {
        if ($organizationId <= 0) {
            return false;
        }

        $context = $requestOrContext instanceof Request
            ? $this->contextForRequest($requestOrContext)
            : $requestOrContext;

        if ($context === null) {
            return false;
        }

        return $context->hasSystemRole(self::SYSTEM_ADMIN)
            || $context->hasOrganizationRole(self::ORGANIZATION_ADMIN, $organizationId);
    }

    /**
     * Return null for global system admin or a list of allowed organization ids.
     *
     * @return list<int>|null
     */
    public function organizationScopeForRequest(Request $request): ?array
    {
        $context = $this->contextForRequest($request);

        if ($context === null || $context->hasSystemRole(self::SYSTEM_ADMIN)) {
            return null;
        }

        return $context->organizationIdsForRole(self::ORGANIZATION_ADMIN);
    }

    /**
     * Assert organization access for create/list helper operations.
     */
    public function assertCanAccessOrganization(
        Request $request,
        int $organizationId,
        string $action = 'access',
        string $resourceType = 'organization',
        int|string|null $resourceReference = null
    ): void {
        $this->assertCanAccessResource($request, $organizationId, $resourceType, $resourceReference, $action);
    }

    /**
     * Assert access to a resource owned by one organization.
     */
    public function assertCanAccessResource(
        Request $request,
        int $organizationId,
        string $resourceType,
        int|string|null $resourceReference = null,
        string $action = 'access'
    ): void {
        $context = $this->contextForRequest($request);

        if ($context === null || $organizationId <= 0) {
            $this->auditDenied($request, $organizationId, $resourceType, $resourceReference, $action, 'missing_context');

            throw new NotFoundException();
        }

        $request->setAuthorizationContext($context->withResourceOrganizationId($organizationId));

        if ($context->hasSystemRole(self::SYSTEM_ADMIN)) {
            $this->auditSystemAdminGlobalAccess($request, $organizationId, $resourceType, $resourceReference, $action);

            return;
        }

        if ($context->hasOrganizationRole(self::ORGANIZATION_ADMIN, $organizationId)) {
            return;
        }

        $this->auditDenied($request, $organizationId, $resourceType, $resourceReference, $action, 'cross_tenant');

        throw new NotFoundException();
    }

    /**
     * @param list<string> $requiredRoles
     */
    public function canPassRoute(Request $request, array $requiredRoles): bool
    {
        $context = $this->contextForRequest($request);

        if ($context === null) {
            return false;
        }

        return $context->hasAnyRole($requiredRoles);
    }

    private function auditDenied(
        Request $request,
        int $organizationId,
        string $resourceType,
        int|string|null $resourceReference,
        string $action,
        string $reasonCode
    ): void {
        $this->auditService->record(
            $reasonCode === 'cross_tenant' ? 'cross_tenant_access_denied' : 'authorization_denied',
            $request->authenticatedUserId(),
            $resourceType,
            is_int($resourceReference) ? $resourceReference : null,
            $request->ipAddress(),
            $request->userAgent(),
            [
                'organization_id' => $organizationId,
                'resource_reference' => is_string($resourceReference) ? $resourceReference : null,
                'action' => $action,
                'reason_code' => $reasonCode,
                'result' => 'denied',
            ]
        );
    }

    private function auditSystemAdminGlobalAccess(
        Request $request,
        int $organizationId,
        string $resourceType,
        int|string|null $resourceReference,
        string $action
    ): void {
        $this->auditService->record(
            'system_admin_global_access',
            $request->authenticatedUserId(),
            $resourceType,
            is_int($resourceReference) ? $resourceReference : null,
            $request->ipAddress(),
            $request->userAgent(),
            [
                'organization_id' => $organizationId,
                'resource_reference' => is_string($resourceReference) ? $resourceReference : null,
                'action' => $action,
                'result' => 'allowed',
            ]
        );
    }
}
