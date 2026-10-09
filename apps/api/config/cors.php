<?php

// Cross-origin requests (CORS) are switched off. The web app is served from the same origin as
// the API (Bible §8.1, work pack K-19), and machines call the API from their own servers, where
// CORS does not apply. Without this file Laravel's built-in default would answer
// `Access-Control-Allow-Origin: *` on api/*, letting any website's JavaScript call the API.

declare(strict_types=1);

return [

    // No path sends CORS headers, so browsers block every cross-origin call.
    'paths' => [],

    'allowed_methods' => [],

    'allowed_origins' => [],

    'allowed_origins_patterns' => [],

    'allowed_headers' => [],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
