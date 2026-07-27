<?php

namespace App\Services\Api\Auth;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

use App\Enums\NonModelActions;
use App\Events\LoginEvents;
use App\Models\User;

/*
 * Encapsulates the core mobile API authentication logic.
 * Enforces stateless token generation and specific field access constraints.
 */
class MobileAuthService
{
    // --- CORE PROCESSES ---

    /*
     * Orchestrates the mobile login attempt.
     * Implements strict rate limiting, verifies credentials via hash, and issues a secure Sanctum token.
     */
    public function processLogin(array $credentials, string $ip): array
    {
        $username = strtolower((string) $credentials['username']);
        $user = User::query()->where('username', $username)->first();

        // 1. Check Database Lockout
        if ($lockoutMessage = $this->handleDatabaseLockout($user)) {
            return ['success' => false, 'status' => Response::HTTP_LOCKED, 'message' => $lockoutMessage];
        }

        $rateLimitKey = 'api-login:' . sha1($username . '|' . $ip);
        
        // 2. Check Rate Limiter (5th Attempt triggers lockout)
        if (RateLimiter::tooManyAttempts($rateLimitKey, 4)) {
            $message = $this->handleRateLimitExceeded($user, $rateLimitKey);
            return ['success' => false, 'status' => Response::HTTP_TOO_MANY_REQUESTS, 'message' => $message];
        }

        // 3. Attempt Authentication (Stateless comparison)
        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($rateLimitKey, 60);
            LoginEvents::dispatch(NonModelActions::LOGIN_FAILED, null);
            return ['success' => false, 'status' => Response::HTTP_UNAUTHORIZED, 'message' => 'The provided credentials do not match our records.'];
        }

        // 4. Post-Authentication Security Checks
        RateLimiter::clear($rateLimitKey);
        
        if (!$user->is_active) {
            LoginEvents::dispatch(NonModelActions::LOGIN_ACCOUNT_DEACTIVATED, $user);
            return ['success' => false, 'status' => Response::HTTP_FORBIDDEN, 'message' => 'User account is deactivated. Please contact the system administrator.'];
        }

        // Role Gatekeeping: Reject web portals (Admin, CWD)
        $userRoleSlug = $user->role->slug_identifier;
        $allowedMobileRoles = ['foreman', 'field_personnel'];
        
        if (!in_array($userRoleSlug, $allowedMobileRoles)) {
            LoginEvents::dispatch(NonModelActions::LOGIN_FAILED, $user);
            return ['success' => false, 'status' => Response::HTTP_FORBIDDEN, 'message' => 'Access Denied: Web-based accounts cannot access the mobile application.'];
        }

        // 5. Finalize Session and Log Success
        $user->updateQuietly(['last_login' => now()]);
        LoginEvents::dispatch(NonModelActions::LOGIN_SUCCESS, $user);
        
        // 6. Delete previous tokens (Only one active token per mobile user)
        $user->tokens()->delete();

        // 7. Generate Sanctum Token & Payload
        $token = $user->createToken($credentials['device_name'])->plainTextToken;

        return [
            'success' => true,
            'status' => Response::HTTP_OK,
            'token' => $token,
            // Return the raw model instance loaded with necessary relations for the Resource mapping
            'user' => $user->load(['role', 'department'])
        ];
    }

    // --- PRIVATE HELPER METHODS ---

    /*
     * Checks if the user model has an active database-level lockout timestamp applied.
     */
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

    /*
     * Applies a database lockout if the request-based rate limiter indicates suspicious spam behavior.
     */
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