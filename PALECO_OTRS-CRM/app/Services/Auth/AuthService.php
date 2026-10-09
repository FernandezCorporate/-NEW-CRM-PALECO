<?php

namespace App\Services\Auth;

use App\Concerns\HandlesAuthSecurity;
use App\Enums\NonModelActions;
use App\Events\LoginEvents;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Service encapsulating the core web authentication logic, rate limiting,
 * database lockouts, role verification, and smart redirection.
 */
class AuthService
{
    use HandlesAuthSecurity;

    // --- AUTHENTICATION & SESSION METHODS ---

    /**
     * Orchestrates the entire login attempt process.
     * Evaluates security constraints sequentially before finalizing the session and determining the redirect.
     */
    public function processLogin(array $credentials, string $ip, string $expectedRole, Request $request): array
    {
        $username = strtolower((string) $credentials['username']);
        $user = User::query()->where('username', $username)->first();

        // 1. Check Database Lockout
        if ($lockoutMessage = $this->handleDatabaseLockout($user)) {
            return ['success' => false, 'message' => $lockoutMessage];
        }

        $rateLimitKey = 'login:'.sha1($username.'|'.$ip);

        // 2. Check Rate Limiter (5th Attempt)
        if (RateLimiter::tooManyAttempts($rateLimitKey, 4)) {
            $message = $this->handleRateLimitExceeded($user, $rateLimitKey);

            return ['success' => false, 'message' => $message];
        }

        // 3. Attempt Authentication
        if (! Auth::attempt($credentials)) {
            RateLimiter::hit($rateLimitKey, 60);
            LoginEvents::dispatch(NonModelActions::LOGIN_FAILED, null);

            return ['success' => false, 'message' => 'The provided credentials do not match our records.'];
        }

        // 4. Post-Authentication Security Checks
        RateLimiter::clear($rateLimitKey);
        $loggedUser = Auth::user();
        $userRoleSlug = $loggedUser->role->slug_identifier;

        if ($userRoleSlug !== $expectedRole) {
            LoginEvents::dispatch(NonModelActions::LOGIN_FAILED, $loggedUser);
            $this->terminateSession($request);

            return ['success' => false, 'message' => 'Access Denied: You do not have permission to access this specific portal.'];
        }

        if (! $loggedUser->is_active) {
            LoginEvents::dispatch(NonModelActions::LOGIN_ACCOUNT_DEACTIVATED, $loggedUser);
            $this->terminateSession($request);

            return ['success' => false, 'message' => 'User account is deactivated. Please contact system administrator.'];
        }

        $landingRoute = $this->getLandingRoute($userRoleSlug);
        if (! $landingRoute) {
            $this->terminateSession($request);

            return ['success' => false, 'message' => 'Access Denied: Your account role does not have web portal privileges.'];
        }

        // 5. Finalize Session and Log Success
        $request->session()->regenerate();
        $loggedUser->updateQuietly(['last_login' => now()]);
        LoginEvents::dispatch(NonModelActions::LOGIN_SUCCESS, $loggedUser);

        // 6. Calculate Smart Redirect
        $redirectUrl = $this->calculateSmartRedirect($request, $userRoleSlug, $landingRoute);

        return ['success' => true, 'redirect_url' => $redirectUrl];
    }

    /**
     * Safely flushes and invalidates the user's web session upon logout or forced termination.
     */
    public function terminateSession(Request $request): void
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    // --- PRIVATE HELPER METHODS ---

    /**
     * Maps the user's role string to their designated dashboard route.
     */
    private function getLandingRoute(string $roleSlug): ?string
    {
        return match ($roleSlug) {
            'admin' => route('admin.dashboard'),
            'cwd_officer' => route('cwd.dashboard'),
            default => null
        };
    }

    /**
     * Intercepts "intended" URLs to prevent users from cross-pollinating into invalid administrative areas after logging in.
     */
    private function calculateSmartRedirect(Request $request, string $userRoleSlug, string $landingRoute): string
    {
        $intendedUrl = $request->session()->pull('url.intended', $landingRoute);

        if ($userRoleSlug === 'admin' && ! str_contains($intendedUrl, '/admin')) {
            return $landingRoute;
        }

        if ($userRoleSlug === 'cwd_officer' && ! str_contains($intendedUrl, '/cwd')) {
            return $landingRoute;
        }

        return $intendedUrl;
    }
}
