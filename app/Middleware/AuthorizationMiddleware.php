<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\RoleRepository;
use App\Services\AuditService;

/**
 * Enforces server-side role requirements for protected backend routes.
 */
final class AuthorizationMiddleware implements MiddlewareInterface
{
    /**
     * @param list<string> $requiredRoles
     */
    public function __construct(
        private readonly array $requiredRoles,
        private readonly RoleRepository $roleRepository = new RoleRepository(),
        private readonly AuditService $auditService = new AuditService()
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        $userId = $request->authenticatedUserId();

        if ($userId === null) {
            $this->auditAuthorizationFailed(null, $request, 'missing_authenticated_user');

            return Response::text('Forbidden', 403);
        }

        if (!$this->roleRepository->userHasAnySystemRole($userId, $this->requiredRoles)) {
            $this->auditAuthorizationFailed($userId, $request, 'missing_required_role');
            $this->auditUnauthorizedAdminAccess($userId, $request);

            return Response::text('Forbidden', 403);
        }

        return $next($request);
    }

    /**
     * Record a generic authorization failure.
     */
    private function auditAuthorizationFailed(?int $userId, Request $request, string $reasonCode): void
    {
        $this->auditService->record(
            'authorization_failed',
            $userId,
            'route',
            null,
            $request->ipAddress(),
            $request->userAgent(),
            [
                'path' => $request->path(),
                'reason_code' => $reasonCode,
                'required_roles' => $this->requiredRoles,
                'result' => 'denied',
            ]
        );
    }

    /**
     * Record denied access to administrative routes.
     */
    private function auditUnauthorizedAdminAccess(int $userId, Request $request): void
    {
        $this->auditService->record(
            'unauthorized_admin_access',
            $userId,
            'route',
            null,
            $request->ipAddress(),
            $request->userAgent(),
            [
                'path' => $request->path(),
                'result' => 'denied',
            ]
        );
    }
}
