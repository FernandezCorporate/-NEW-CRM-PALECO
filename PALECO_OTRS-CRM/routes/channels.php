<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('cwd.operations', function (User $user) {
    return in_array($user->role->slug_identifier, ['cwd_officer', 'admin'], true);
});