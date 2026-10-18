<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\ActivityPolicy;
use Illuminate\Auth\Access\Response;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;
use Spatie\Activitylog\Facades\Activity;
use Spatie\Activitylog\Models\Activity as ActivityModel;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

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
        if (app()->environment('production') || str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

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

        // --- API RATE LIMITERS ---

        RateLimiter::for('api-login', function (Request $request) {
            $username = strtolower((string) ($request->input('username') ?? ''));
            $key = ($username !== '' ? $username : 'guest').'|'.$request->ip();

            return Limit::perMinute(10)
                ->by($key)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Too many login attempts. Please try again in a minute.',
                    ], HttpResponse::HTTP_TOO_MANY_REQUESTS, $headers);
                });
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Too many requests. Please slow down.',
                    ], HttpResponse::HTTP_TOO_MANY_REQUESTS, $headers);
                });
        });

        RateLimiter::for('api-sync', function (Request $request) {
            // Allows burst capacity (120 requests/minute) when mobile app flushes offline queued items
            return Limit::perMinute(120)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Sync rate limit exceeded. Please wait before syncing more items.',
                    ], HttpResponse::HTTP_TOO_MANY_REQUESTS, $headers);
                });
        });
    }
}
