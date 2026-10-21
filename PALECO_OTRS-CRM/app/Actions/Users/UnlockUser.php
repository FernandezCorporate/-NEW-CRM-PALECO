<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Single-Action: Administratively bypasses and clears an account security lockout.
 */
class UnlockUser
{
    public function handle(User $user): void
    {
        $this->execute($user);
    }

    public function execute(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->locked_until = null;
            $user->save();

            $normalizedUsername = strtolower($user->username);
            RateLimiter::clear('auth:account:attempts:'.$normalizedUsername);
            RateLimiter::clear('auth:account:locked:'.$normalizedUsername);

            $causer = auth()->user();
            activity('Users')
                ->performedOn($user)
                ->causedBy($causer)
                ->event('account_unlocked')
                ->withProperties([
                    'ip_address' => request()->ip() ?? 'unknown',
                    'user_agent' => request()->userAgent() ?? 'unknown',
                    'target_user_id' => $user->id,
                    'username' => $user->username,
                    'role' => $user->role?->role_name,
                    'action' => 'admin_lockout_bypass',
                ])
                ->log("Administrative lockout bypass granted for {$user->username}.");
        });
    }
}
