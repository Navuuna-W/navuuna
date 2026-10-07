<?php

// The 429 every rate limiter answers with once a caller is over its limit. The limiters are
// defined in App\Providers\AuthServiceProvider; this class only builds the reply.

declare(strict_types=1);

namespace App\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Builds the 429 body: limit, window and when to retry. Implements work pack K-11 (PRD 9.6).
 */
class TooManyRequestsResponse
{
    /** Every limiter counts calls per minute. */
    public const WINDOW_SECONDS = 60;

    /**
     * @param  int  $limit  How many calls the limiter allows per window.
     */
    public function __construct(private readonly int $limit) {}

    /**
     * Called by Laravel's throttle middleware with the headers it already worked out
     * (Retry-After, X-RateLimit-Limit, X-RateLimit-Remaining); they go on the reply too.
     *
     * @param  array<string, int|string>  $headers
     */
    public function __invoke(Request $request, array $headers): JsonResponse
    {
        return new JsonResponse([
            'message' => 'Too many requests.',
            'limit' => $this->limit,
            'window_seconds' => self::WINDOW_SECONDS,
            'retry_after_seconds' => (int) $headers['Retry-After'],
        ], JsonResponse::HTTP_TOO_MANY_REQUESTS, $headers);
    }
}
