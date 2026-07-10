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

    public function view(User $user): bool { return $user->role->slug_identifier === 'admin'; }
    public function update(User $user): bool { return $user->role->slug_identifier === 'admin'; }
    public function deleteConfirm(User $user): bool { return $user->role->slug_identifier === 'admin'; }
    public function archive(User $user): bool { return $user->role->slug_identifier === 'admin'; }
    public function restore(User $user): bool { return $user->role->slug_identifier === 'admin'; }
    public function forceDelete(User $user): bool { return $user->role->slug_identifier === 'admin'; }
}
