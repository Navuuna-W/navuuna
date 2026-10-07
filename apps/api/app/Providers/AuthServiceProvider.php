<?php

// Wires up how callers authenticate and how often they may call: registers the `api-key`
// guard driver (config/auth.php) and the `api`, `tiles` and `login` rate limiters used as
// `throttle:<name>` on routes. Loaded from bootstrap/providers.php.

declare(strict_types=1);

namespace App\Providers;

use App\Auth\FindUserByApiKey;
use App\Auth\TooManyRequestsResponse;
use App\Models\ApiKey;
use Illuminate\Auth\AuthManager;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the X-Api-Key guard and the rate limiters. Implements work pack K-11 and NFR-05.
 */
class AuthServiceProvider extends ServiceProvider
{
    /** NFR-05: 60 requests per minute per API key. */
    private const API_REQUESTS_PER_MINUTE = 60;

    /** One map load fetches dozens of tiles (docs/contracts/tiles.md §7); K-18 will measure it. */
    private const TILE_REQUESTS_PER_MINUTE = 600;

    /** Slows password guessing to 5 tries a minute for one email from one address. */
    private const LOGIN_ATTEMPTS_PER_MINUTE = 5;

    /**
     * Teach Laravel the `api-key` driver and define the three limiters.
     */
    public function boot(AuthManager $authManager, RateLimiter $rateLimiter): void
    {
        $authManager->viaRequest('api-key', new FindUserByApiKey);

        $rateLimiter->for('api', fn (Request $request) => $this->limitPerMinute(
            self::API_REQUESTS_PER_MINUTE, $this->apiCallerId($request)
        ));
        $rateLimiter->for('tiles', fn (Request $request) => $this->limitPerMinute(
            self::TILE_REQUESTS_PER_MINUTE, $this->signedInCallerId($request)
        ));
        $rateLimiter->for('login', fn (Request $request) => $this->limitPerMinute(
            self::LOGIN_ATTEMPTS_PER_MINUTE, 'login:'.$request->string('email').'|'.$request->ip()
        ));
    }

    /**
     * A per-minute limit counted per $callerId, answering with the K-11 429 body.
     */
    private function limitPerMinute(int $limit, string $callerId): Limit
    {
        return Limit::perMinute($limit)
            ->by($callerId)
            ->response(new TooManyRequestsResponse($limit));
    }

    /**
     * Who to count an API call against: the key itself (NFR-05: per key), else the signed-in
     * user, else the IP address. The key is hashed so it never lands in the cache in plain.
     */
    private function apiCallerId(Request $request): string
    {
        $plainKey = $request->header(FindUserByApiKey::HEADER);
        if (is_string($plainKey) && $plainKey !== '') {
            return 'key:'.ApiKey::hashKey($plainKey);
        }

        return $this->signedInCallerId($request);
    }

    /**
     * Who to count a web-app call against: the signed-in user, else the IP address.
     */
    private function signedInCallerId(Request $request): string
    {
        $user = $request->user();
        if ($user !== null) {
            return 'user:'.$user->getAuthIdentifier();
        }

        return 'ip:'.$request->ip();
    }
}
