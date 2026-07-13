<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\CsrfTokenManager;
use App\Core\ModelException;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\UserRepository;

/**
 * Renders the minimal protected admin dashboard foundation.
 */
final class AdminDashboardController extends BaseController
{
    private readonly CsrfTokenManager $csrfTokenManager;

    public function __construct(
        private readonly UserRepository $userRepository = new UserRepository(),
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
        return new self(new UserRepository());
    }

    /**
     * Show the minimal admin dashboard for authorized system administrators.
     */
    public function index(Request $request): Response
    {
        $userId = $request->authenticatedUserId();

        if ($userId === null) {
            return $this->redirect('/login');
        }

        try {
            $user = $this->userRepository->findById($userId);
        } catch (ModelException) {
            return $this->redirect('/login');
        }

        $userData = $user->toArray();
        $email = $this->stringValue($userData['email'] ?? null);
        $displayName = $this->displayName($userData);

        return $this->viewWithLayout('admin/dashboard', 'layouts/admin', [
            'pageTitle' => 'Admin',
            'displayName' => $displayName !== '' ? $displayName : $email,
            'email' => $email,
            'csrfToken' => $this->csrfTokenManager->generateToken($request),
        ]);
    }

    /**
     * Build a display name from safe user fields.
     *
     * @param array<string, mixed> $userData
     */
    private function displayName(array $userData): string
    {
        $name = trim(implode(' ', array_filter([
            $this->stringValue($userData['first_name'] ?? null),
            $this->stringValue($userData['last_name'] ?? null),
        ])));

        return $name;
    }

    /**
     * Return a scalar value as string without exposing internal fields.
     */
    private function stringValue(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
