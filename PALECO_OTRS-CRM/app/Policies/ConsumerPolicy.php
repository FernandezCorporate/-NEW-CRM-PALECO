<?php

namespace App\Policies;

use App\Models\User;

class ConsumerPolicy
{
    public function viewAny(User $user): bool { return in_array($user->role->slug_identifier, ['admin', 'cwd_officer']); }
    public function view(User $user): bool { return in_array($user->role->slug_identifier, ['admin', 'cwd_officer']); }
}
