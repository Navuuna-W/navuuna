<?php

// JSON routes under /api. Requests from the web app's own origin get a session and CSRF
// check (statefulApi() in bootstrap/app.php); `auth:sanctum` answers 401 when nobody is signed in.

declare(strict_types=1);

use App\Http\Controllers\Auth\SessionController;
use Illuminate\Support\Facades\Route;

// Its own limiter, so password guessing can't use up the API or tile buckets (work pack K-11).
Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [SessionController::class, 'destroy']);
});
