<?php

// Wires up how machines authenticate: registers the `api-key` guard driver that
// config/auth.php's `api_key` guard uses. Loaded from bootstrap/providers.php.

declare(strict_types=1);

namespace App\Providers;

use App\Auth\FindUserByApiKey;
use Illuminate\Auth\AuthManager;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the X-Api-Key guard. Implements work pack K-11.
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * Teach Laravel the `api-key` driver: each request is checked by FindUserByApiKey.
     */
    public function boot(AuthManager $authManager): void
    {
        $authManager->viaRequest('api-key', new FindUserByApiKey);
    }
}
