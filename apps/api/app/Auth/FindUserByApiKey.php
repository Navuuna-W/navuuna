<?php

// The `api_key` guard: reads X-Api-Key, hashes it and finds the api_client user it belongs to.
// Registered in App\Providers\AuthServiceProvider; routes use it with `auth:api_key`.

declare(strict_types=1);

namespace App\Auth;

use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Turns a request's X-Api-Key into a user, or null. Implements work pack K-11 (X-Api-Key guard).
 */
class FindUserByApiKey
{
    /** The header machines send their key in (Bible §11). */
    public const HEADER = 'X-Api-Key';

    /**
     * Return the api_client user for the request's key, or null when the key is missing,
     * unknown, or its user is no longer an api_client (Laravel then answers 401).
     */
    public function __invoke(Request $request): ?User
    {
        $plainKey = $request->header(self::HEADER);
        if (! is_string($plainKey) || $plainKey === '') {
            return null;
        }

        $apiKey = ApiKey::where('key_hash', ApiKey::hashKey($plainKey))->first();
        if ($apiKey === null) {
            return null;
        }

        // Changing a user's role away from api_client switches their keys off.
        if ($apiKey->user->role !== Role::ApiClient) {
            return null;
        }

        return $apiKey->user;
    }
}
