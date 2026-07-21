<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Spatie\Activitylog\Facades\Activity;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;

/*
 * Bootstraps core application services and global configurations.
 * Defines authorization gates for role-based access control (RBAC).
 * Intercepts Spatie Activitylog events to automatically inject global request metadata.
 */
class AppServiceProvider extends ServiceProvider
{
    /*
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /*
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        /*
         * Sets up 'access-admin' and 'access-cwd_officer' gates that obscure unauthorized access by returning a 404 Not Found.
         */
        Gate::define('access-admin', function (User $user) {
            return $user->role->slug_identifier === 'admin'
                ? Response::allow()
                : Response::denyAsNotFound();
        });
        Gate::define('access-cwd_officer', function (User $user) {
            return $user->role->slug_identifier === 'cwd_officer'
                ? Response::allow()
                : Response::denyAsNotFound();
        });

        /*
         * Appends IP address and User Agent to all activity logs generated via HTTP requests.
         */
        Activity::beforeLogging(function (ActivityContract $activity) {
            if (!app()->runningInConsole()){
                $activity->properties = $activity->properties
                    ->put('ip_address', request()->ip())
                    ->put('user_agent', request()->userAgent());
            }
        });
    }
}