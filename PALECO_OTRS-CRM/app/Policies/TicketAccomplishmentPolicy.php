<?php

namespace App\Policies;

use App\Models\User;

class TicketAccomplishmentPolicy
{
    public function view(User $user): bool { return $user->role->slug_identifier === 'cwd_officer'; }
}
