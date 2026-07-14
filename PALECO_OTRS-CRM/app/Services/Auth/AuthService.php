<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use App\Models\User;
use App\Events\LoginEvents;
use App\Enums\NonModelActions;
use Illuminate\Http\Request;

class AuthService
{
    /**
     * Processes the entire login attempt and returns a structured array with the result.
     */
    public function processLogin(array $credentials, string $ip, string $expectedRole, Request $request): array
    {
        $username = strtolower((string) $credentials['username']);
        $user = User::query()->where('username', $username)->first();

        // 1. Check Database Lockout
        if ($lockoutMessage = $this->handleDatabaseLockout($user)) {
            return ['success' => false, 'message' => $lockoutMessage];
        }

        $rateLimitKey = 'login:' . sha1($username . '|' . $ip);
        
        // 2. Check Rate Limiter (5th Attempt)
        if (RateLimiter::tooManyAttempts($rateLimitKey, 4)) {
            $message = $this->handleRateLimitExceeded($user, $rateLimitKey);
            return ['success' => false, 'message' => $message];
        }

        // 3. Attempt Authentication
        if (!Auth::attempt($credentials)) {
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

        if (!$loggedUser->is_active) {
            LoginEvents::dispatch(NonModelActions::LOGIN_ACCOUNT_DEACTIVATED, $loggedUser);
            $this->terminateSession($request);
            return ['success' => false, 'message' => 'User account is deactivated. Please contact system administrator.'];
        }

        $landingRoute = $this->getLandingRoute($userRoleSlug);
        if (!$landingRoute) {
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
     * Terminates a session safely.
     */
    public function terminateSession(Request $request): void
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    /* -------------------------------------------------------------------------
     * PRIVATE HELPER METHODS
     * ------------------------------------------------------------------------- */

    private function getLandingRoute(string $roleSlug): ?string
    {
        return match($roleSlug) {
            'admin' => route('admin.dashboard'),
            'cwd_officer' => route('cwd.dashboard'),
            default => null 
        };
    }

    private function calculateSmartRedirect(Request $request, string $userRoleSlug, string $landingRoute): string
    {
        $intendedUrl = $request->session()->pull('url.intended', $landingRoute);

        if ($userRoleSlug === 'admin' && !str_contains($intendedUrl, '/admin')) {
            return $landingRoute;
        } 
        
        if ($userRoleSlug === 'cwd_officer' && !str_contains($intendedUrl, '/cwd')) {
            return $landingRoute;
        }

        return $intendedUrl;
    }

    private function handleDatabaseLockout(?User $user): ?string
    {
        if (!$user || !$user->locked_until) return null;

        if ($user->locked_until > now()) {
            LoginEvents::dispatch(NonModelActions::LOGIN_FAILED, $user);
            $minutesLeft = max(1, ceil(now()->diffInMinutes($user->locked_until)));
            return "This account is temporarily locked. Please wait {$minutesLeft} minute(s).";
        }

        $user->updateQuietly(['locked_until' => null]);
        return null;
    }

    private function handleRateLimitExceeded(?User $user, string $rateLimitKey): string
    {
        if ($user && !$user->locked_until) {
            $user->updateQuietly(['locked_until' => now()->addMinutes(15)]);
            LoginEvents::dispatch(NonModelActions::LOGIN_FAILED, $user);
            $minutesLeft = max(1, ceil(now()->diffInMinutes($user->locked_until)));
            
            return "This account is temporarily locked due to multiple failed attempts. Please wait {$minutesLeft} minute(s).";
        }
        
        LoginEvents::dispatch(NonModelActions::LOGIN_FAILED, $user);
        $availableAgain = RateLimiter::availableIn($rateLimitKey);
        
        return "Too many attempts. Try again after {$availableAgain} seconds.";
    }
}