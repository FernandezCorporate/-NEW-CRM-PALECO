<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Profiles\ProfileController;
use App\Http\Controllers\Api\Tickets\TicketController;
use App\Http\Controllers\Api\Teams\TeamController;

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
    
    // --- SHARED MOBILE ENDPOINTS (Accessible by all authenticated mobile users) ---
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user/profile', [ProfileController::class, 'show']);
    
    Route::prefix('tickets')->group(function () {
        Route::get('/', [TicketController::class, 'index']);
    });

    // --- FOREMAN SPECIFIC ENDPOINTS ---
    Route::middleware('can:access-foreman')->group(function () {
        
        Route::prefix('tickets')->group(function () {
            Route::post('/{ticket}/assign', [TicketController::class, 'assign']);
            // Future Foreman endpoints go here (e.g., escalate, close, prioritize)
        });

        Route::prefix('teams')->group(function () {
            Route::get('/form-options/{team?}', [TeamController::class, 'formOptions'])->whereUlid('team');

            Route::get('/', [TeamController::class, 'index'])->withTrashed();
            Route::get('/{team}', [TeamController::class, 'show'])->withTrashed();

            Route::post('/create', [TeamController::class, 'store']);
            Route::put('/{team}/update', [TeamController::class, 'update'])->withTrashed();

            Route::delete('/{team}/archive', [TeamController::class, 'archive'])->withTrashed();

            Route::patch('/{team}/restore', [TeamController::class, 'restore'])->whereUlid('team')->withTrashed();
            Route::delete('/{team}/force-delete', [TeamController::class, 'destroy'])->name('admin.teams.destroy')->whereUlid('team')->withTrashed();
        });
    });

    // --- FIELD PERSONNEL SPECIFIC ENDPOINTS ---
    Route::middleware('can:access-field_personnel')->group(function () {
        
        Route::prefix('tickets')->group(function () {
            // Future Field Personnel endpoints go here (e.g., PATCH /{ticket}/status, POST /{ticket}/resolve)
        });

    });
});