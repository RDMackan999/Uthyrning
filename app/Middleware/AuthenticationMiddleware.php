<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\CookieManager;
use App\Core\MiddlewareInterface;
use App\Core\ModelException;
use App\Core\RedirectResponse;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;
use App\Models\UserSession;
use App\Repositories\UserRepository;
use App\Services\SessionService;

/**
 * Verifies server-side sessions before protected routes run.
 */
final class AuthenticationMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly CookieManager $cookieManager = new CookieManager('uthyrning_session', 28800, false),
        private readonly SessionService $sessionService = new SessionService(),
        private readonly UserRepository $userRepository = new UserRepository()
    ) {
    }

    /**
     * Create middleware using configured cookie settings.
     */
    public static function fromConfig(): self
    {
        return new self(CookieManager::fromConfig());
    }

    public function handle(Request $request, callable $next): Response
    {
        $sessionToken = $this->cookieManager->sessionToken($request);

        if ($sessionToken === null) {
            return $this->redirectToLogin();
        }

        $session = $this->sessionService->findByToken($sessionToken);

        if ($session === null || !$this->sessionService->isValid($session)) {
            $this->cookieManager->deleteSessionCookie();

            return $this->redirectToLogin();
        }

        $userId = $this->sessionUserId($session);

        if ($userId === null || !$this->isActiveUser($userId)) {
            $this->cookieManager->deleteSessionCookie();

            return $this->redirectToLogin();
        }

        $this->sessionService->touch($sessionToken);
        $request->setAuthenticatedUserId($userId);

        return $next($request);
    }

    /**
     * Read the user id from a validated session model.
     */
    private function sessionUserId(UserSession $session): ?int
    {
        $userId = $session->toArray()['user_id'] ?? null;

        return is_numeric($userId) ? (int) $userId : null;
    }

    /**
     * Ensure the session still belongs to an active, non-deleted user.
     */
    private function isActiveUser(int $userId): bool
    {
        try {
            $user = $this->userRepository->findById($userId);
        } catch (ModelException) {
            return false;
        }

        return $this->isActive($user);
    }

    /**
     * Check the user status without exposing account details.
     */
    private function isActive(User $user): bool
    {
        $data = $user->toArray();

        return ($data['status_key'] ?? null) === 'active'
            && ($data['deleted_at'] ?? null) === null;
    }

    /**
     * Redirect unauthenticated requests without technical details.
     */
    private function redirectToLogin(): Response
    {
        return new RedirectResponse('/login');
    }
}
