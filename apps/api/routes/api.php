<?php

// JSON routes under /api. Requests from the web app's own origin get a session and CSRF
// check (statefulApi() in bootstrap/app.php); `auth:sanctum` answers 401 when nobody is signed in.

declare(strict_types=1);

use App\Http\Controllers\Api\V1\CurrentUserController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Findings\FlagController;
use App\Http\Controllers\Findings\FlagTransitionController;
use Illuminate\Support\Facades\Route;

// Its own limiter, so password guessing can't use up the API or tile buckets (work pack K-11).
Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [SessionController::class, 'destroy']);
});

// Who is signed in and what they may open (DEC-13, work pack A-10). Session only: a machine
// holding an X-Api-Key has no screens to choose between, and the sign-in screen's 401 should
// read "Unauthenticated", not "send an API key" (App\Auth\MissingApiKeyResponse).
Route::prefix('v1')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/me', CurrentUserController::class);
});

// The JSON API (Bible §11): the web app signs in with its session, machines with X-Api-Key;
// both share the 60/min limiter (NFR-05).
Route::prefix('v1')->middleware(['auth:sanctum,api_key', 'throttle:api'])->group(function () {
    Route::get('/flags', [FlagController::class, 'index']);
    Route::get('/flags/{flag}', [FlagController::class, 'show'])->whereUuid('flag');
    Route::post('/flags/{flag}/transition', FlagTransitionController::class)->whereUuid('flag');
});
