<?php

// GET /api/v1/me — the read-back after sign-in. The sign-in screen (PRD S7) calls it to learn
// whether a session exists, and the header and router use the answer to decide which screens
// are offered. Guests never get here: `auth:sanctum` on the route answers 401 first.

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\CurrentUserResource;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

/**
 * Hands the signed-in user to CurrentUserResource. Implements DEC-13 (`GET /me`) and
 * work pack A-10 (role-aware routes: a viewer must not be offered the review screens).
 */
class CurrentUserController
{
    /**
     * Return who is signed in and what they may open.
     *
     * @throws AuthenticationException when no user is attached to the request (401).
     */
    public function __invoke(Request $request): CurrentUserResource
    {
        // auth:sanctum has already answered 401 to guests; this keeps the type honest.
        $user = $request->user();
        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return new CurrentUserResource($user);
    }
}
