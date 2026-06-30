<?php

namespace App\Providers;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use App\Models\User;
use App\Enums\UserRoles;
use Illuminate\Auth\Access\Response;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('access-admin', function (User $user) {
            return $user->role === UserRoles::ADMIN
                ? Response::allow()
                : Response::denyAsNotFound();
        });

        Gate::define('access-cwd_officer', function (User $user) {
            return $user->role === UserRoles::CWD
                ? Response::allow()
                : Response::denyAsNotFound();
        });
    }
}
