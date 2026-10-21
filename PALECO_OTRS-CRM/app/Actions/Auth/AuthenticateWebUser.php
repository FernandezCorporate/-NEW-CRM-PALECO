<?php

namespace App\Actions\Auth;

use App\Concerns\HandlesAuthSecurity;
use App\Enums\NonModelActions;
use App\Events\LoginEvents;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Single-Action: Orchestrates web login authentication, dual-layer security, role validation, and smart redirect.
 */
class AuthenticateWebUser
{
    use HandlesAuthSecurity;

    public function __construct(
        protected TerminateWebSession $terminateSession
    ) {}

    public function handle(array $credentials, string $ip, string $expectedRole, Request $request): array
    {
        return $this->execute($credentials, $ip, $expectedRole, $request);
    }

    public function execute(array $credentials, string $ip, string $expectedRole, Request $request): array
    {
        // 1. Layer 1: Check IP Velocity Rate Limit (Infrastructure defense)
        if ($ipThrottled = $this->checkIpVelocityLimit($ip)) {
            return ['success' => false, 'message' => $ipThrottled['message']];
        }

        $username = strtolower((string) $credentials['username']);
        $user = User::query()->where('username', $username)->first();

        // 2. Layer 2: Check Database & Virtual Account Lockout
        if ($lockoutMessage = $this->handleAccountLockout($user, $username)) {
            return ['success' => false, 'message' => $lockoutMessage];
        }

        // 3. Attempt Authentication
        if (! Auth::attempt($credentials)) {
            LoginEvents::dispatch(NonModelActions::LOGIN_FAILED, null);

            return $this->handleAccountLoginFailure($username, $user);
        }

        // 4. Post-Authentication Security Checks
        $loggedUser = Auth::user();
        $userRoleSlug = $loggedUser->role->slug_identifier;

        // Role Gatekeeping (Oracle Elimination)
        if ($userRoleSlug !== $expectedRole) {
            LoginEvents::dispatch(NonModelActions::LOGIN_FAILED, $loggedUser);
            $this->terminateSession->execute($request);

            return $this->handleAccountLoginFailure($username, $user);
        }

        if (! $loggedUser->is_active) {
            LoginEvents::dispatch(NonModelActions::LOGIN_ACCOUNT_DEACTIVATED, $loggedUser);
            $this->terminateSession->execute($request);

            return ['success' => false, 'message' => 'User account is deactivated. Please contact system administrator.'];
        }

        $landingRoute = $this->getLandingRoute($userRoleSlug);
        if (! $landingRoute) {
            LoginEvents::dispatch(NonModelActions::LOGIN_FAILED, $loggedUser);
            $this->terminateSession->execute($request);

            return $this->handleAccountLoginFailure($username, $user);
        }

        // 5. Finalize Session and Log Success
        $this->clearAccountSecurityTrackers($username, $loggedUser);
        $request->session()->regenerate();
        $loggedUser->updateQuietly(['last_login' => now()]);
        LoginEvents::dispatch(NonModelActions::LOGIN_SUCCESS, $loggedUser);

        // 6. Calculate Smart Redirect
        $redirectUrl = $this->calculateSmartRedirect($request, $userRoleSlug, $landingRoute);

        return ['success' => true, 'redirect_url' => $redirectUrl];
    }

    private function getLandingRoute(string $roleSlug): ?string
    {
        return match ($roleSlug) {
            'admin' => route('admin.dashboard'),
            'cwd_officer' => route('cwd.dashboard'),
            default => null
        };
    }

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
