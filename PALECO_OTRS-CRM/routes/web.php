<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Admin\AdminDashboardController;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Cwd\CwdDashboardController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\TicketCategoryController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Cwd\TicketController;

Route::middleware('guest')->group(function() {
    Route::get('/portal', [AuthController::class, 'showRoleSelection'])->name('portal');
    
    Route::get('/login/{role}', [AuthController::class, 'showLoginForm'])->name('portal.login');
    Route::post('/login/{role}', [AuthController::class, 'login'])->name('attemptLogin');

    Route::get('/login', function() {
        return redirect()->route('portal');
    })->name('login');
});

Route::middleware('auth')->group(function() {
    
    // Root routing gatekeeper
    Route::get('/', function (Request $request) {
        $dashboardRoute = match (Auth::user()->role->slug_identifier) {
            'admin' => 'admin.dashboard',
            'cwd_officer' => 'cwd.dashboard',
            default => null,
        };

        if (!$dashboardRoute) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            
            return redirect()->route('portal')->withErrors(['error' => 'Access Denied: Your account role does not have web portal privileges.']);
        }

        return redirect()->route($dashboardRoute);
    })->name('dashboard');

    Route::prefix('admin')->middleware('can:access-admin')->group(function() {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');

        Route::prefix('users')->group(function() {
            Route::get('/', [UserController::class, 'index'])->name('admin.users');
            Route::get('/{user}', [UserController::class, 'show'])->name('admin.users.show')->whereUlid('user')->withTrashed();

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
            Route::get('/{team}', [TeamController::class, 'show'])->name('admin.teams.show')->whereUlid('team')->withTrashed();

            Route::get('/create', [TeamController::class, 'teamForm'])->name('admin.teams.createForm');
            Route::post('/', [TeamController::class, 'store'])->name('admin.teams.store');

            Route::get('/teams/form/{team}', [TeamController::class, 'teamForm'])->name('admin.teams.editForm')->whereUlid('team'); 
            Route::put('/teams/{team}', [TeamController::class, 'update'])->name('admin.teams.update')->whereUlid('team');

            Route::get('/{team}/archive', [TeamController::class, 'deleteConfirm'])->name('admin.teams.deleteConfirm')->whereUlid('team');
            Route::delete('/{team}', [TeamController::class, 'archive'])->name('admin.teams.archive')->whereUlid('team');

            Route::patch('/{team}/restore', [TeamController::class, 'restore'])->name('admin.teams.restore')->whereUlid('team');

            Route::get('/{team}/delete', [TeamController::class, 'deleteConfirm'])->name('admin.teams.forceDeleteConfirm')->whereUlid('team')->withTrashed();
            Route::delete('/{team}/force-delete', [TeamController::class, 'destroy'])->name('admin.teams.destroy')->whereUlid('team')->withTrashed();
        });

        Route::prefix('ticket-categories')->group(function() {
            Route::get('/', [TicketCategoryController::class, 'viewAny'])->name('admin.ticketCategories');
            Route::get('/{category}', [TicketCategoryController::class, 'show'])->name('admin.ticketCategories.show')->whereNumber('category')->withTrashed();
            
            Route::get('/create', [TicketCategoryController::class, 'ticketCategoryForm'])->name('admin.ticketCategories.createForm');
            Route::post('/', [TicketCategoryController::class, 'store'])->name('admin.ticketCategories.store');

            Route::get('/{category}/edit', [TicketCategoryController::class, 'ticketCategoryForm'])->name('admin.ticketCategories.editForm')->whereNumber('category');
            Route::put('/{category}', [TicketCategoryController::class, 'update'])->name('admin.ticketCategories.update')->whereNumber('category');

            Route::get('/{category}/archive', [TicketCategoryController::class, 'deleteConfirm'])->name('admin.ticketCategories.deleteConfirm')->whereNumber('category');
            Route::delete('/{category}', [TicketCategoryController::class, 'archive'])->name('admin.ticketCategories.archive')->whereNumber('category');

            Route::patch('/{category}/restore', [TicketCategoryController::class, 'restore'])->name('admin.ticketCategories.restore')->whereNumber('category')->withTrashed();

            Route::get('/{category}/delete', [TicketCategoryController::class, 'deleteConfirm'])->name('admin.ticketCategories.forceDeleteConfirm')->whereNumber('category')->withTrashed();
            Route::delete('/{category}/force-delete', [TicketCategoryController::class, 'destroy'])->name('admin.ticketCategories.destroy')->whereNumber('category')->withTrashed();
        });
    });


    Route::middleware('auth')->group(function() {
        Route::prefix('cwd')->middleware('can:access-cwd_officer')->group(function() {
            Route::get('/dashboard', [CwdDashboardController::class, 'index'])->name('cwd.dashboard');

            Route::prefix('tickets')->group(function() {
                Route::get('/', [TicketController::class, 'index'])->name('cwd.tickets');
                Route::get('/create', [TicketController::class, 'ticketForm'])->name('cwd.tickets.createForm');
                
                // Added creation storage processing route
                Route::post('/', [TicketController::class, 'store'])->name('cwd.tickets.store');
            });
        });
    });

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});