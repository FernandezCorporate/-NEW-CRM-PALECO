<?php

namespace App\Policies;

use App\Models\User;

/*
 * Defines access control rules for reviewing, querying, and deciding on cross-department ticket endorsements.
 */
class TicketEndorsementPolicy
{
    /*
     * Determine whether the user can browse pending endorsements in the CWD queue.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('cwd_officer') && $user->hasPermission('endorsements.view');
    }

    /*
     * Determine whether the user can view endorsement details and audit history.
     */
    public function view(User $user): bool
    {
        return $user->hasRole('cwd_officer') && $user->hasPermission('endorsements.view');
    }

    /*
     * Determine whether the user can decide (approve/reject/reroute) an endorsement request.
     */
    public function decide(User $user): bool
    {
        return $user->hasRole('cwd_officer') && $user->hasPermission('endorsements.decide');
    }
}
