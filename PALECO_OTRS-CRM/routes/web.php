<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use Illuminate\Support\Facades\Auth;
use App\Enums\UserRoles;

Route::middleware('guest')->group(function() {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('attemptLogin');
});

Route::middleware('auth')->group(function() {
    Route::get('/', function () {
        return match (Auth::user()->role) {
            UserRoles::ADMIN => redirect()->route('admin.dashboard'),
            default => abort(403),
        };
    })->name('dashboard');

    Route::prefix('admin')->group(function() {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
    });

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});