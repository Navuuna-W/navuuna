<?php

// The 401 a machine gets when its X-Api-Key is missing or wrong. Hooked into exception
// rendering in bootstrap/app.php; session-only routes keep Laravel's default 401.

declare(strict_types=1);

namespace App\Auth;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;

/**
 * Builds the 401 body that names the X-Api-Key header. Implements work pack K-11 (PRD 9.6).
 */
class MissingApiKeyResponse
{
    /**
     * Return the 401 body when the route accepts an API key, or null to let Laravel answer
     * the default way (routes that only take a session).
     */
    public function __invoke(AuthenticationException $exception): ?JsonResponse
    {
        if (! in_array('api_key', $exception->guards(), true)) {
            return null;
        }

        return new JsonResponse([
            'message' => 'Missing or invalid API key. Send it in the '.FindUserByApiKey::HEADER.' header.',
            'header' => FindUserByApiKey::HEADER,
        ], JsonResponse::HTTP_UNAUTHORIZED);
    }
}
