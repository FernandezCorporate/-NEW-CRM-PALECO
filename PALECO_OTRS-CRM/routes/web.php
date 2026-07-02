<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Admin\AdminDashboardController;
use Illuminate\Support\Facades\Auth;
use App\Enums\UserRoles;
use App\Http\Controllers\Cwd\CwdDashboardController;
use App\Http\Controllers\Admin\DepartmentController;

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

        Route::prefix('departments')->group(function() {
            Route::get('/', [DepartmentController::class, 'index'])->name('admin.departments');
            Route::get('/{dept}', [DepartmentController::class, 'show'])->name('admin.departments.show')->whereNumber('dept')->withTrashed();
            
            Route::get('/create', [DepartmentController::class, 'departmentForm'])->name('admin.departments.createForm');
            Route::post('/', [DepartmentController::class, 'store'])->name('admin.departments.store');

            Route::get('/{dept}/edit', [DepartmentController::class, 'departmentForm'])->name('admin.departments.editForm')->whereNumber('dept');
            Route::put('/{dept}', [DepartmentController::class, 'update'])->name('admin.departments.update')->whereNumber('dept');

            Route::get('/{dept}/archive', [DepartmentController::class, 'deleteConfirm'])->name('admin.departments.deleteConfirm')->whereNumber('dept');
            Route::delete('/{dept}', [DepartmentController::class, 'archive'])->name('admin.departments.archive')->whereNumber('dept');

            Route::patch('{dept}/restore', [DepartmentController::class, 'restore'])->name('admin.departments.restore')->whereNumber('dept')->withTrashed();

            Route::get('/{dept}/delete', [DepartmentController::class, 'deleteConfirm'])->name('admin.departments.forceDeleteConfirm')->whereNumber('dept')->withTrashed();
            Route::delete('/{dept}/force-delete', [DepartmentController::class, 'destroy'])->name('admin.departments.destroy')->whereNumber('dept')->withTrashed();
        });
    });

    Route::prefix('cwd')->middleware('can:access-cwd_officer')->group(function() {
        Route::get('/dashboard', [CwdDashboardController::class, 'index'])->name('cwd.dashboard');
    });

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});