<?php
namespace App\Observers;

use App\Models\User;
use App\Enums\UserRoles;
use Illuminate\Support\Facades\DB;

class UserObserver
{
    /**
     * Handle the User "saved" event.
     * Fires after changes are staged, safely handling the pivot drop.
     */
    public function saved(User $user): void
    {
        // Check if the role attribute was modified during this cycle
        if ($user->wasChanged('role')) {
            // Get what the role used to be before the update
            $oldRole = $user->getOriginal('role');

            // Handle both string and Enum backed value matching safely
            $oldRoleValue = $oldRole instanceof UserRoles ? $oldRole->value : $oldRole;

            if ($oldRoleValue === UserRoles::FIELD_PERSONNEL->value) {
                // Execute immediately after the controller transaction successfully commits
                DB::afterCommit(function () use ($user) {
                    $user->teams()->detach();
                });
            }
        }
    }
}
