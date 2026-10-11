<?php

namespace App\Concerns;

use App\Enums\NonModelActions;
use App\Events\LoginEvents;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Trait providing decoupled dual-layer authentication protection:
 * Layer 1: Network IP velocity limiting (infrastructure/DoS defense).
 * Layer 2: Account identity failure tracking & database lockout (credential protection).
 */
trait HandlesAuthSecurity
{
    /**
     * Layer 1: Evaluates IP velocity to protect authentication endpoints against network-level flood attacks.
     * Operates completely independent of user credentials and does not leak account state.
     */
    protected function checkIpVelocityLimit(string $ip, int $maxRequests = 10, int $decaySeconds = 60): ?array
    {
        $ipKey = 'auth:ip:'.$ip;

        if (RateLimiter::tooManyAttempts($ipKey, $maxRequests)) {
            $seconds = RateLimiter::availableIn($ipKey);

            return [
                'blocked' => true,
                'message' => "Too many attempts from this network. Please wait {$seconds} second(s).",
                'available_in' => $seconds,
            ];
        }

        RateLimiter::hit($ipKey, $decaySeconds);

        return null;
    }

    /**
     * Checks if the account has an active database-level or virtual cache lockout applied.
     */
    protected function handleAccountLockout(?User $user, string $username = ''): ?string
    {
        // 1. Check virtual cache lockout (protects both real and non-existent/gibberish accounts)
        if ($username !== '') {
            $lockKey = 'auth:account:locked:'.strtolower($username);
            if (RateLimiter::tooManyAttempts($lockKey, 1)) {
                LoginEvents::dispatch(NonModelActions::LOGIN_FAILED, $user);
                $secondsLeft = RateLimiter::availableIn($lockKey);
                $minutesLeft = max(1, ceil($secondsLeft / 60));

                return "This account is temporarily locked. Please wait {$minutesLeft} minute(s).";
            }
        }

        // 2. Check database lockout for existing user model
        if (! $user || ! $user->locked_until) {
            return null;
        }

        if ($user->locked_until > now()) {
            LoginEvents::dispatch(NonModelActions::LOGIN_FAILED, $user);
            $minutesLeft = max(1, ceil(now()->diffInMinutes($user->locked_until)));

            return "This account is temporarily locked. Please wait {$minutesLeft} minute(s).";
        }

        $user->updateQuietly(['locked_until' => null]);

        return null;
    }

    /**
     * Backward-compatible alias for handleAccountLockout.
     */
    protected function handleDatabaseLockout(?User $user): ?string
    {
        return $this->handleAccountLockout($user, $user?->username ?? '');
    }

    /**
     * Layer 2: Registers an authentication failure against the targeted account identity.
     * Emits user-facing attempt countdown (4, 3, 2, 1) and enforces 15-minute lockout on the 5th attempt.
     */
    protected function handleAccountLoginFailure(string $username, ?User $user, int $maxAttempts = 5, int $decaySeconds = 900): array
    {
        $normalizedUsername = strtolower($username);
        $accountKey = 'auth:account:attempts:'.$normalizedUsername;
        RateLimiter::hit($accountKey, $decaySeconds);
        $attempts = RateLimiter::attempts($accountKey);

        if ($attempts >= $maxAttempts) {
            if ($user && ! $user->locked_until) {
                $user->updateQuietly(['locked_until' => now()->addMinutes(15)]);
            }

            // Enact 15-minute virtual cache lockout on the username (anti-enumeration parity)
            $lockKey = 'auth:account:locked:'.$normalizedUsername;
            RateLimiter::hit($lockKey, 900);

            RateLimiter::clear($accountKey);

            return [
                'success' => false,
                'locked' => true,
                'message' => 'This account is temporarily locked due to multiple failed attempts. Please wait 15 minute(s).',
            ];
        }

        $retriesLeft = $maxAttempts - $attempts;

        return [
            'success' => false,
            'locked' => false,
            'retries_left' => $retriesLeft,
            'message' => "The provided credentials do not match our records. You have {$retriesLeft} attempt(s) remaining.",
        ];
    }

    /**
     * Clears account-level failure tracking and resets lockout on successful authentication.
     */
    protected function clearAccountSecurityTrackers(string $username, ?User $user): void
    {
        $normalizedUsername = strtolower($username);
        RateLimiter::clear('auth:account:attempts:'.$normalizedUsername);
        RateLimiter::clear('auth:account:locked:'.$normalizedUsername);

        if ($user && $user->locked_until) {
            $user->updateQuietly(['locked_until' => null]);
        }
    }
}
