<?php

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('cwd.operations', function (User $user) {
    return in_array($user->role?->slug_identifier, ['cwd_officer', 'admin', 'supervisor'], true);
}, ['guards' => ['web', 'sanctum']]);

Broadcast::channel('team.{teamId}', function (User $user, string $teamId) {
    $role = $user->role?->slug_identifier;

    if ($role === 'field_personnel') {
        return $user->teams()->where('teams.id', $teamId)->exists();
    }

    if ($role === 'supervisor') {
        return Team::where('id', $teamId)->where('department_id', $user->department_id)->exists();
    }

    return in_array($role, ['cwd_officer', 'admin'], true);
}, ['guards' => ['web', 'sanctum']]);

Broadcast::channel('department.{deptId}', function (User $user, $deptId) {
    $role = $user->role?->slug_identifier;

    if ($role === 'supervisor') {
        return (int) $user->department_id === (int) $deptId;
    }

    return in_array($role, ['cwd_officer', 'admin'], true);
}, ['guards' => ['web', 'sanctum']]);

// Personal channel: account notices (deactivation, department moves) and team membership changes,
// so an app learns it was added to or removed from a team even before it joins that team's channel
Broadcast::channel('user.{userId}', function (User $user, string $userId) {
    return $user->id === $userId;
}, ['guards' => ['web', 'sanctum']]);
