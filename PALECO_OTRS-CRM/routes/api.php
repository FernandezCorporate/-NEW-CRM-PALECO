<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\Auth\AuthController;

/*
|--------------------------------------------------------------------------
| Mobile API Routes
|--------------------------------------------------------------------------
| Routes tailored specifically for stateless consumption by the Flutter app.
*/

/*
 * Public API Endpoints
 * Open specifically for unauthenticated guests to request access tokens.
 */
Route::post('/login', [AuthController::class, 'login'])->middleware('guest');

/*
 * Authenticated API Endpoints
 * Strictly guarded by Laravel Sanctum; requires a valid Bearer token in the header.
 */
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
});