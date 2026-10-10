<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Dashboard\DashboardController;
use App\Http\Controllers\Api\Profiles\ProfileController;
use App\Http\Controllers\Api\Remarks\TicketRemarkController;
use App\Http\Controllers\Api\Teams\TeamController;
use App\Http\Controllers\Api\Tickets\TicketAccomplishmentController;
use App\Http\Controllers\Api\Tickets\TicketAssignmentController;
use App\Http\Controllers\Api\Tickets\TicketController;
use App\Http\Controllers\Api\Tickets\TicketEndorsementController;
use Illuminate\Support\Facades\Route;

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

    // --- SHARED MOBILE ENDPOINTS (Supervisor & Field Personnel) ---
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user/profile', [ProfileController::class, 'show']);

    Route::prefix('tickets')->group(function () {
        Route::get('/', [TicketController::class, 'index']);
        Route::get('/{ticket}', [TicketController::class, 'show'])->whereUlid('ticket');

        Route::get('/{ticket}/accomplishments', [TicketAccomplishmentController::class, 'index']);
        Route::get('/{ticket}/accomplishments/{accomplishment}', [TicketAccomplishmentController::class, 'show']);

        Route::get('/{ticket}/remarks', [TicketRemarkController::class, 'index'])->whereUlid('ticket');
        Route::post('/{ticket}/remarks', [TicketRemarkController::class, 'store'])->whereUlid('ticket');
    });

    // --- SUPERVISOR SPECIFIC ENDPOINTS ---
    Route::middleware('can:access-supervisor')->group(function () {

        Route::get('/dashboard', [DashboardController::class, 'supervisorIndex']);

        Route::prefix('tickets')->group(function () {
            Route::get('/{ticket}/assign-options', [TicketAssignmentController::class, 'assignOptions']);
            Route::post('/{ticket}/assign', [TicketAssignmentController::class, 'assign']);

            Route::get('/{ticket}/endorse-options', [TicketEndorsementController::class, 'endorsementOptions']);
            Route::post('/{ticket}/endorse', [TicketEndorsementController::class, 'endorse']);

            Route::post('/{ticket}/accomplishments/{accomplishment}/verify', [TicketAccomplishmentController::class, 'verify']);

            Route::get('/{ticket}/history', [TicketController::class, 'history'])->whereUlid('ticket');
        });

        Route::prefix('teams')->group(function () {
            Route::get('/team-options', [TeamController::class, 'formOptions']);

            Route::get('/', [TeamController::class, 'index'])->withTrashed();
            Route::post('/', [TeamController::class, 'store']);
            Route::post('/create', [TeamController::class, 'store']); // Compatibility alias

            Route::get('/{team}', [TeamController::class, 'show'])->withTrashed();
            Route::put('/{team}', [TeamController::class, 'update'])->withTrashed();
            Route::put('/{team}/update', [TeamController::class, 'update'])->withTrashed(); // Compatibility alias

            Route::delete('/{team}/archive', [TeamController::class, 'archive'])->withTrashed();
            Route::patch('/{team}/restore', [TeamController::class, 'restore'])->whereUlid('team')->withTrashed();
            Route::delete('/{team}/force-delete', [TeamController::class, 'destroy'])->whereUlid('team')->withTrashed();
        });
    });

    // --- FIELD PERSONNEL SPECIFIC ENDPOINTS (OFFLINE-ASYNC SUPPORTED) ---
    Route::middleware(['can:access-field_personnel', 'idempotent'])->group(function () {

        Route::prefix('tickets')->group(function () {
            Route::patch('/{ticket}/start', [TicketController::class, 'start']);
            Route::post('/{ticket}/accomplish', [TicketAccomplishmentController::class, 'store']);
        });

    });
});
