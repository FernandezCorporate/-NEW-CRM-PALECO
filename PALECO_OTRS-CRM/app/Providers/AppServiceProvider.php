<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\ActivityPolicy;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;
use Spatie\Activitylog\Facades\Activity;
use Spatie\Activitylog\Models\Activity as ActivityModel;

/**
 * Bootstraps core application services and global configurations.
 * Defines authorization gates for role-based access control (RBAC).
 * Intercepts Spatie Activitylog events to automatically inject global request metadata.
 */
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
        // --- WEB RBAC GATES ---

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

        Gate::policy(ActivityModel::class, ActivityPolicy::class);

        // --- MOBILE API RBAC GATES ---

        Gate::define('access-supervisor', function (User $user) {
            return $user->role->slug_identifier === 'supervisor'
                ? Response::allow()
                : Response::denyAsNotFound();
        });

        Gate::define('access-field_personnel', function (User $user) {
            return $user->role->slug_identifier === 'field_personnel'
                ? Response::allow()
                : Response::denyAsNotFound();
        });

        // --- AUDIT LOGGING METADATA HOOK ---

        Activity::beforeLogging(function (ActivityContract $activity) {
            if (! app()->runningInConsole()) {
                $activity->properties = $activity->properties
                    ->put('ip_address', request()->ip())
                    ->put('user_agent', request()->userAgent());
            }
        });
    }
}
