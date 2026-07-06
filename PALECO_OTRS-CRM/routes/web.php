<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Admin\AdminDashboardController;
use Illuminate\Support\Facades\Auth;
use App\Enums\UserRoles;
use App\Http\Controllers\Cwd\CwdDashboardController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\UserController;
use App\Models\Team;
use App\Models\User;

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

        Route::prefix('users')->group(function() {
            Route::get('/', [UserController::class, 'index'])->name('admin.users');

            Route::get('/create', [UserController::class, 'userForm'])->name('admin.users.createForm');
            Route::post('/', [UserController::class, 'store'])->name('admin.users.store');

            Route::get('/{user}/edit', [UserController::class, 'userForm'])->name('admin.users.editForm')->whereUlid('user');
            Route::put('/{user}', [UserController::class, 'update'])->name('admin.users.update')->whereUlid('user');

            Route::get('/{user}/deactivate', [UserController::class, 'deactivateConfirm'])->name('admin.users.deactivateConfirm')->whereUlid('user');
            Route::patch('/{user}/deactivate', [UserController::class, 'deactivate'])->name('admin.users.deactivate')->whereUlid('user');

            Route::get('/{user}/reactivate', [UserController::class, 'reactivateConfirm'])->name('admin.users.reactivateConfirm')->whereUlid('user');
            Route::patch('/{user}/reactivate', [UserController::class, 'reactivate'])->name('admin.users.reactivate')->whereUlid('user');          
        });

        Route::prefix('departments')->group(function() {
            Route::get('/', [DepartmentController::class, 'index'])->name('admin.departments');
            Route::get('/{dept}', [DepartmentController::class, 'show'])->name('admin.departments.show')->whereNumber('dept')->withTrashed();
            
            Route::get('/create', [DepartmentController::class, 'departmentForm'])->name('admin.departments.createForm');
            Route::post('/', [DepartmentController::class, 'store'])->name('admin.departments.store');

            Route::get('/{dept}/edit', [DepartmentController::class, 'departmentForm'])->name('admin.departments.editForm')->whereNumber('dept');
            Route::put('/{dept}', [DepartmentController::class, 'update'])->name('admin.departments.update')->whereNumber('dept');

            Route::get('/{dept}/archive', [DepartmentController::class, 'deleteConfirm'])->name('admin.departments.deleteConfirm')->whereNumber('dept');
            Route::delete('/{dept}', [DepartmentController::class, 'archive'])->name('admin.departments.archive')->whereNumber('dept');

            Route::patch('/{dept}/restore', [DepartmentController::class, 'restore'])->name('admin.departments.restore')->whereNumber('dept')->withTrashed();

            Route::get('/{dept}/delete', [DepartmentController::class, 'deleteConfirm'])->name('admin.departments.forceDeleteConfirm')->whereNumber('dept')->withTrashed();
            Route::delete('/{dept}/force-delete', [DepartmentController::class, 'destroy'])->name('admin.departments.destroy')->whereNumber('dept')->withTrashed();
        });

        Route::prefix('teams')->group(function() {
            Route::get('/', [TeamController::class, 'index'])->name('admin.teams');

            Route::get('/create', [TeamController::class, 'teamForm'])->name('admin.teams.createForm');
            Route::post('/', [TeamController::class, 'store'])->name('admin.teams.store');

            Route::get('/teams/form/{team}', [TeamController::class, 'teamForm'])->name('admin.teams.editForm')->whereUlid('team'); 
            Route::put('/teams/{team}', [TeamController::class, 'update'])->name('admin.teams.update')->whereUlid('team');

            Route::get('/{team}/delete', [TeamController::class, 'deleteConfirm'])->name('admin.teams.deleteConfirm')->whereUlid('team');
            Route::delete('/{team}', [TeamController::class, 'archive'])->name('admin.teams.archive')->whereUlid('team');
        });
    });

    Route::prefix('cwd')->middleware('can:access-cwd_officer')->group(function() {
        Route::get('/dashboard', [CwdDashboardController::class, 'index'])->name('cwd.dashboard');
    });

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});