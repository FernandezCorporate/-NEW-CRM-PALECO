<?php

namespace App\Policies;

use App\Models\User;

class TicketCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->slug_identifier === 'admin';
    }

    public function ticketCategoryForm(User $user): bool
    {
        return $user->role->slug_identifier === 'admin';
    }

    public function create(User $user): bool
    {
        return $user->role->slug_identifier === 'admin';
    }
}
