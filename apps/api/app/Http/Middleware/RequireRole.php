<?php

// Route middleware `role:analyst,admin`: lets a request through only if the signed-in user has
// one of the listed roles. Runs after `auth:sanctum`, which has already answered 401 to guests.

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Auth\Role;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Answers 403 when the user's role is not in the route's list. Implements FR-20 (roles).
 * Example: Route::middleware(['auth:sanctum', 'role:analyst,admin'])->get(...).
 */
class RequireRole
{
    /**
     * @param  Closure(Request): Response  $next
     * @param  string  ...$allowedRoleNames  Role values, e.g. 'analyst', 'admin'.
     *
     * @throws AuthenticationException when nobody is signed in (401).
     * @throws AccessDeniedHttpException when the user's role is not allowed (403).
     */
    public function handle(Request $request, Closure $next, string ...$allowedRoleNames): Response
    {
        $user = $request->user();
        if ($user === null) {
            throw new AuthenticationException;
        }

        // Role::from fails loudly on a typo in a route definition instead of silently denying.
        $allowedRoles = array_map(fn (string $roleName) => Role::from($roleName), $allowedRoleNames);
        if (! in_array($user->role, $allowedRoles, true)) {
            throw new AccessDeniedHttpException('Your role cannot use this page.');
        }

        return $next($request);
    }
}
