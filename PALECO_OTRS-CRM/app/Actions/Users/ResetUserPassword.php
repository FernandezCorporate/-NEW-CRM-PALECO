<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;

/**
 * Single-Action: Administratively resets a user's password, revoking active sessions/tokens and clearing lockouts.
 */
class ResetUserPassword
{
    public function handle(User $user, string $newPassword): void
    {
        $this->execute($user, $newPassword);
    }

    public function execute(User $user, string $newPassword): void
    {
        DB::transaction(function () use ($user, $newPassword) {
            $user->password = $newPassword;
            $user->remember_token = null;
            $user->save();

            // Revoke active mobile API tokens
            $user->tokens()->delete();

            // Revoke active web database sessions for this user
            if (Schema::hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }

            // Clear any active lockout state on this account
            if ($user->locked_until) {
                $user->updateQuietly(['locked_until' => null]);
            }

            $normalizedUsername = strtolower($user->username);
            RateLimiter::clear('auth:account:attempts:'.$normalizedUsername);
            RateLimiter::clear('auth:account:locked:'.$normalizedUsername);

            // Record standardized audit trail event
            $causer = auth()->user();
            activity('Users')
                ->performedOn($user)
                ->causedBy($causer)
                ->event('password_reset')
                ->withProperties([
                    'ip_address' => request()->ip() ?? 'unknown',
                    'user_agent' => request()->userAgent() ?? 'unknown',
                    'target_user_id' => $user->id,
                    'username' => $user->username,
                    'role' => $user->role?->role_name,
                    'action' => 'admin_password_reset',
                ])
                ->log("Password was administratively reset for {$user->username}.");
        });
    }
}

