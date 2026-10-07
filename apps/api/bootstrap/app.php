<?php

declare(strict_types=1);

use App\Auth\MissingApiKeyResponse;
use App\Http\Middleware\RequireRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The web app is served from the same origin, so it signs in with a session cookie
        // instead of a token (work pack K-11, Sanctum SPA).
        $middleware->statefulApi();

        // `role:analyst,admin` on a route answers 403 to every other role (FR-20).
        $middleware->alias(['role' => RequireRole::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Machines get a 401 that names the X-Api-Key header (work pack K-11).
        $exceptions->render(new MissingApiKeyResponse);
    })->create();
