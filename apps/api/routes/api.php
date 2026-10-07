<?php

// JSON routes under /api. Requests from the web app's own origin get a session and CSRF
// check (statefulApi() in bootstrap/app.php); `auth:sanctum` answers 401 when nobody is signed in.

declare(strict_types=1);

use App\Http\Controllers\Auth\SessionController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [SessionController::class, 'store']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [SessionController::class, 'destroy']);
});
