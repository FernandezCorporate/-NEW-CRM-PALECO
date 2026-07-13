<?php

namespace App\Policies;

use App\Models\User;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role->slug_identifier, ['cwd_officer', 'admin'], true);
    }

    public function ticketForm(User $user): bool
    {
        return $user->role->slug_identifier === 'cwd_officer';
    }
}
