<?php

namespace App\Actions\Auth;

use App\Concerns\HandlesAuthSecurity;
use App\Enums\NonModelActions;
use App\Events\LoginEvents;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

/**
 * Single-Action: Orchestrates mobile API login authentication, rate limiting, and Sanctum token issuance.
 */
class AuthenticateMobileUser
{
    use HandlesAuthSecurity;

    public function handle(array $credentials, string $ip): array
    {
        return $this->execute($credentials, $ip);
    }

    public function execute(array $credentials, string $ip): array
    {
        // 1. Layer 1: Check IP Velocity Rate Limit (Infrastructure defense)
        if ($ipThrottled = $this->checkIpVelocityLimit($ip)) {
            return [
                'success' => false,
                'status' => Response::HTTP_TOO_MANY_REQUESTS,
                'message' => $ipThrottled['message'],
            ];
        }

        $username = strtolower((string) $credentials['username']);
        $user = User::query()->where('username', $username)->first();

        // 2. Layer 2: Check Database & Virtual Account Lockout
        if ($lockoutMessage = $this->handleAccountLockout($user, $username)) {
            return [
                'success' => false,
                'status' => Response::HTTP_LOCKED,
                'message' => $lockoutMessage,
            ];
        }

        // 3. Attempt Authentication (Stateless comparison)
        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            LoginEvents::dispatch(NonModelActions::LOGIN_FAILED, null);
            $failure = $this->handleAccountLoginFailure($username, $user);

            return [
                'success' => false,
                'status' => ($failure['locked'] ?? false) ? Response::HTTP_LOCKED : Response::HTTP_UNAUTHORIZED,
                'message' => $failure['message'],
            ];
        }

        // 4. Post-Authentication Security Checks
        if (! $user->is_active) {
            LoginEvents::dispatch(NonModelActions::LOGIN_ACCOUNT_DEACTIVATED, $user);

            return [
                'success' => false,
                'status' => Response::HTTP_FORBIDDEN,
                'message' => 'User account is deactivated. Please contact the system administrator.',
            ];
        }

        // Role Gatekeeping: Reject web portals (Admin, CWD) - Oracle Elimination
        $userRoleSlug = $user->role->slug_identifier;
        $allowedMobileRoles = ['supervisor', 'field_personnel'];

        if (! in_array($userRoleSlug, $allowedMobileRoles)) {
            LoginEvents::dispatch(NonModelActions::LOGIN_FAILED, $user);
            $failure = $this->handleAccountLoginFailure($username, $user);

            return [
                'success' => false,
                'status' => ($failure['locked'] ?? false) ? Response::HTTP_LOCKED : Response::HTTP_UNAUTHORIZED,
                'message' => $failure['message'],
            ];
        }

        // 5. Finalize Session and Log Success
        $this->clearAccountSecurityTrackers($username, $user);
        $user->updateQuietly(['last_login' => now()]);
        LoginEvents::dispatch(NonModelActions::LOGIN_SUCCESS, $user);

        // 6. Delete previous tokens (Only one active token per mobile user)
        $user->tokens()->delete();

        // 7. Generate Sanctum Token & Payload
        $token = $user->createToken($credentials['device_name'])->plainTextToken;

        $user->load(['role', 'department']);
        if ($userRoleSlug === 'field_personnel') {
            $user->load(['teams.department']);
        }

        return [
            'success' => true,
            'status' => Response::HTTP_OK,
            'token' => $token,
            'user' => $user,
        ];
    }
}

