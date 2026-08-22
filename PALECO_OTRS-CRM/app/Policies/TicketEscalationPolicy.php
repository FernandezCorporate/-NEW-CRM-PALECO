<?php

namespace App\Policies;

use App\Models\User;

class TicketEscalationPolicy
{
    public function viewAny(User $user): bool { return $user->role->slug_identifier === "cwd_officer"; } 
}
