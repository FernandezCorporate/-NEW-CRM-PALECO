<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Admin\AdminDashboardController;
use Illuminate\Support\Facades\Auth;
use App\Enums\UserRoles;
use App\Http\Controllers\Cwd\CwdDashboardController;

Route::middleware('guest')->group(function() {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('attemptLogin');
});

Route::middleware('auth')->group(function() {
    Route::get('/', function () {
        return match (Auth::user()->role) {
            UserRoles::ADMIN => redirect()->route('admin.dashboard'),
            UserRoles::CWD => redirect()->route('cwd.dashboard'),
            default => abort(403),
        };
    })->name('dashboard');

    Route::prefix('admin')->middleware('can:access-admin')->group(function() {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    });

    Route::prefix('cwd')->middleware('can:access-cwd_officer')->group(function() {
        Route::get('/dashboard', [CwdDashboardController::class, 'index'])->name('cwd.dashboard');
    });

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});