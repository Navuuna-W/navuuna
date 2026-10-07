<?php

// Signs a person in and out of the web app with a Sanctum session cookie (same origin, CSRF).
// The browser first calls GET /sanctum/csrf-cookie, then POST /api/login; every later
// request carries the session cookie. API clients never come here: they use X-Api-Key (K-11b).

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Auth\Role;
use App\Models\User;
use Illuminate\Auth\SessionGuard;
use Illuminate\Container\Attributes\Auth;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * Starts and ends a session. Implements work pack K-11 (Sanctum SPA, same origin, CSRF).
 */
class SessionController
{
    /**
     * @param  SessionGuard  $sessionGuard  The cookie-based `web` guard Sanctum checks first.
     */
    public function __construct(
        #[Auth('web')] private readonly SessionGuard $sessionGuard,
    ) {}

    /**
     * Sign in with email and password. Returns 204 and a fresh session on success.
     *
     * A wrong password and an api_client account get the same 422, so the reply never
     * tells a stranger which emails have accounts or what role they have.
     */
    public function store(Request $request): Response
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $isSignedIn = $this->sessionGuard->attemptWhen($credentials, $this->isAllowedASession(...));
        if (! $isSignedIn) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        // A new session ID after sign-in stops session fixation (OWASP, NFR-05).
        $request->session()->regenerate();

        return response()->noContent();
    }

    /**
     * Sign out: end the session and issue a new CSRF token. Returns 204.
     */
    public function destroy(Request $request): Response
    {
        $this->sessionGuard->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    /**
     * True for every role that uses the web app. API clients authenticate with a key instead.
     */
    private function isAllowedASession(User $user): bool
    {
        return $user->role !== Role::ApiClient;
    }
}
