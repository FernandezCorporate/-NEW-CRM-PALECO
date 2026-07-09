<?php
namespace App\Observers;

use App\Models\User;
use App\Models\Role;
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
        if ($user->wasChanged('role_id')) {
            // Get what the role used to be before the update
            $oldRoleId = $user->getOriginal('role_id');

            if ($oldRoleId) {
                $oldRole = Role::find($oldRoleId);
                
                if ($oldRole && $oldRole->slug_identifier === 'field_personnel') {
                    // Execute immediately after the controller transaction successfully commits
                    DB::afterCommit(function () use ($user) {
                        $user->teams()->detach();
                    });
                }
            }
        }
    }
}