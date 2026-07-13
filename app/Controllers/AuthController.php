<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\CookieManager;
use App\Core\CsrfTokenManager;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthenticationService;
use App\Services\SessionService;
use Throwable;

/**
 * Handles the first backend login flow for administration.
 */
final class AuthController extends BaseController
{
    private const LOGIN_ERROR = 'Inloggningen misslyckades. Kontrollera uppgifterna och försök igen.';

    private readonly CookieManager $cookieManager;

    private readonly CsrfTokenManager $csrfTokenManager;

    public function __construct(
        private readonly AuthenticationService $authenticationService = new AuthenticationService(),
        private readonly SessionService $sessionService = new SessionService(),
        ?CookieManager $cookieManager = null,
        ?CsrfTokenManager $csrfTokenManager = null,
    ) {
        parent::__construct();

        $this->cookieManager = $cookieManager ?? CookieManager::fromConfig();
        $this->csrfTokenManager = $csrfTokenManager ?? CsrfTokenManager::fromConfig();
    }

    /**
     * Show the login form.
     */
    public function showLogin(Request $request): Response
    {
        return $this->renderLogin($request);
    }

    /**
     * Attempt login with email and password.
     */
    public function login(Request $request): Response
    {
        if (!$this->csrf()->validate($request, $this->stringPost($request, 'csrf_token'))) {
            return $this->renderLogin($request, true);
        }

        try {
            $result = $this->authenticationService->attemptLogin(
                $this->stringPost($request, 'email'),
                $this->stringPost($request, 'password'),
                $request->ipAddress(),
                $request->userAgent(),
            );
        } catch (Throwable) {
            return $this->renderLogin($request, true);
        }

        if (($result['success'] ?? false) !== true || !is_string($result['session_token'] ?? null)) {
            return $this->renderLogin($request, true);
        }

        $this->cookieManager()->setSessionCookie((string) $result['session_token']);

        return $this->redirect('/admin');
    }

    /**
     * Revoke the current session and remove the session cookie.
     */
    public function logout(Request $request): Response
    {
        if (!$this->csrf()->validate($request, $this->stringPost($request, 'csrf_token'))) {
            return $this->redirect('/login');
        }

        $sessionToken = $this->cookieManager()->sessionToken($request);

        if ($sessionToken !== null) {
            $this->sessionService->revoke($sessionToken);
        }

        $this->cookieManager()->deleteSessionCookie();

        return $this->redirect('/login');
    }

    /**
     * Show a minimal authenticated admin route.
     */
    public function admin(Request $request): Response
    {
        if (!$this->hasValidSession($request)) {
            $this->cookieManager()->deleteSessionCookie();

            return $this->redirect('/login');
        }

        $csrfToken = $this->csrf()->generateToken($request);
        $escapedToken = htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8');

        return Response::html(
            '<!doctype html><html lang="sv"><head><meta charset="utf-8"><title>Admin</title></head><body>'
            . '<main><h1>Admin authenticated</h1>'
            . '<form method="post" action="/logout">'
            . '<input type="hidden" name="csrf_token" value="' . $escapedToken . '">'
            . '<button type="submit">Logga ut</button>'
            . '</form></main></body></html>'
        );
    }

    /**
     * Render the login form with a fresh CSRF token.
     */
    private function renderLogin(Request $request, bool $showError = false): Response
    {
        return $this->view('auth/login', [
            'csrfToken' => $this->csrf()->generateToken($request),
            'errorMessage' => $showError ? self::LOGIN_ERROR : null,
        ]);
    }

    /**
     * Validate the current session cookie against server-side session state.
     */
    private function hasValidSession(Request $request): bool
    {
        $sessionToken = $this->cookieManager()->sessionToken($request);

        if ($sessionToken === null) {
            return false;
        }

        $session = $this->sessionService->findByToken($sessionToken);
        if ($session === null || !$this->sessionService->isValid($session)) {
            return false;
        }

        $this->sessionService->touch($sessionToken);

        return true;
    }

    /**
     * Read POST values as trimmed strings.
     */
    private function stringPost(Request $request, string $key): string
    {
        $value = $request->post($key, '');

        return is_string($value) ? trim($value) : '';
    }

    /**
     * Resolve the configured cookie manager.
     */
    private function cookieManager(): CookieManager
    {
        return $this->cookieManager;
    }

    /**
     * Resolve the configured CSRF token manager.
     */
    private function csrf(): CsrfTokenManager
    {
        return $this->csrfTokenManager;
    }
}
