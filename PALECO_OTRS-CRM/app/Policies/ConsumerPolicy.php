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
        return $user->hasRole('admin', 'cwd_officer') && $user->hasPermission('consumers.view');
    }

    /*
     * Determine whether the user can inspect a specific consumer's account details.
     */
    public function view(User $user): bool
    {
        return $user->hasRole('admin', 'cwd_officer') && $user->hasPermission('consumers.view');
    }
}
