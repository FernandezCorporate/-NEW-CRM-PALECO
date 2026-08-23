<?php

namespace App\Policies;

use App\Models\User;

class TicketEscalationPolicy
{
    public function viewAny(User $user): bool { return $user->role->slug_identifier === "cwd_officer"; } 
    public function view(User $user): bool { return $user->role->slug_identifier === "cwd_officer"; }
    public function decide(User $user): bool { return $user->role->slug_identifier === "cwd_officer"; }
}
