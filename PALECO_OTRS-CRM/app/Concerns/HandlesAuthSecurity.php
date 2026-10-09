<?php

namespace App\Concerns;

use App\Enums\NonModelActions;
use App\Events\LoginEvents;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Trait providing shared database-level lockout verification and rate-limiting enforcement
 * for web and mobile authentication pipelines.
 */
trait HandlesAuthSecurity
{
    /**
     * Checks if the user model has an active database-level lockout timestamp applied.
     */
    protected function handleDatabaseLockout(?User $user): ?string
    {
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
     * Applies a database lockout if the request-based rate limiter indicates suspicious spam behavior.
     */
    protected function handleRateLimitExceeded(?User $user, string $rateLimitKey): string
    {
        if ($user && ! $user->locked_until) {
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
