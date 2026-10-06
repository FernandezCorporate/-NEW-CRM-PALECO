<?php

namespace App\Policies;

use App\Models\User;

/*
 * Defines access control rules for viewing and querying utility consumer records.
 */
class ConsumerPolicy
{
    /*
     * Determine whether the user can browse the consumer directory.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role->slug_identifier, ['admin', 'cwd_officer'], true);
    }

    /*
     * Determine whether the user can inspect a specific consumer's account details.
     */
    public function view(User $user): bool
    {
        return in_array($user->role->slug_identifier, ['admin', 'cwd_officer'], true);
    }
}
