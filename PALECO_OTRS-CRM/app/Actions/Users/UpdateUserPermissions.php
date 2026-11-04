<?php

namespace App\Actions\Users;

use App\Enums\TicketStatus;
use App\Models\Permission;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Permissions\PermissionCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Single-Action: Replaces the permissions of a user account with the checked list.
 * Refuses a change that would leave a department or a crew with nobody able to move its tickets forward.
 */
class UpdateUserPermissions
{
    // Tickets that still need a supervisor's assignment or verification
    private const DEPARTMENT_UNFINISHED = [
        TicketStatus::OPEN,
        TicketStatus::ASSIGNED,
        TicketStatus::IN_PROGRESS,
        TicketStatus::PENDING_ENDORSEMENT,
        TicketStatus::RESOLVED,
    ];

    // Tickets a crew still has to start or report on
    private const TEAM_UNFINISHED = [
        TicketStatus::ASSIGNED,
        TicketStatus::IN_PROGRESS,
    ];

    /**
     * @param  array<int, string>  $names  the permission names that stay checked
     * @return bool whether anything changed
     *
     * @throws ValidationException
     */
    public function execute(User $user, array $names, User $actor, bool $isNewAccount = false): bool
    {
        $names = array_values(array_unique($names));
        $allowed = PermissionCatalog::forRole($user->role?->slug_identifier);

        $foreign = array_diff($names, $allowed);
        if ($foreign) {
            throw ValidationException::withMessages([
                'permissions' => 'These permissions do not apply to the '.$user->role?->role_name.' role: '.implode(', ', $foreign).'.',
            ]);
        }

        // An admin can only hand another admin the access they hold themselves
        if ($user->hasRole('admin')) {
            $notHeld = array_diff($names, $actor->permissions->pluck('name')->all());
            if ($notHeld) {
                throw ValidationException::withMessages([
                    'permissions' => 'You cannot give an administrator permissions you do not have: '.implode(', ', $notHeld).'.',
                ]);
            }
        }

        return DB::transaction(function () use ($user, $names, $actor, $isNewAccount) {
            $lockedUser = User::query()->where('id', $user->id)->lockForUpdate()->firstOrFail();
            $current = $lockedUser->permissions()->pluck('name')->all();

            $removed = array_values(array_diff($current, $names));
            $added = array_values(array_diff($names, $current));

            if (! $removed && ! $added) {
                return false;
            }

            if (! $isNewAccount) {
                $this->guardDepartmentWorkflow($lockedUser, $removed);
                $this->guardCrewWorkflow($lockedUser, $removed);
            }

            $lockedUser->permissions()->sync(Permission::whereIn('name', $names)->pluck('id'));
            // Saved quietly: the permission change is logged below, not as a profile edit
            $lockedUser->forceFill(['permissions_updated_at' => now()])->saveQuietly();

            activity()
                ->useLog('Users')
                ->performedOn($lockedUser)
                ->causedBy($actor)
                ->event('permissions_updated')
                ->withProperties([
                    'old' => ['permissions' => array_values($current)],
                    'attributes' => ['permissions' => $names],
                ])
                ->log("{$lockedUser->username} permissions have been modified");

            $user->unsetRelation('permissions');

            return true;
        });
    }

    /**
     * A department with unfinished tickets must keep an active supervisor who can assign and verify them.
     */
    private function guardDepartmentWorkflow(User $user, array $removed): void
    {
        if (! $user->hasRole('supervisor') || ! $user->is_active || ! $user->department_id) {
            return;
        }

        foreach (array_intersect($removed, PermissionCatalog::SUPERVISOR_WORKFLOW) as $permission) {
            $othersHoldIt = User::query()
                ->where('id', '!=', $user->id)
                ->where('department_id', $user->department_id)
                ->where('is_active', true)
                ->whereHas('role', fn ($q) => $q->where('slug_identifier', 'supervisor'))
                ->whereHas('permissions', fn ($q) => $q->where('name', $permission))
                ->lockForUpdate()
                ->exists();

            if ($othersHoldIt) {
                continue;
            }

            $unfinished = Ticket::query()
                ->where('department_id', $user->department_id)
                ->whereIn('status', self::DEPARTMENT_UNFINISHED)
                ->count();

            if ($unfinished > 0) {
                throw ValidationException::withMessages([
                    'permissions' => "Cannot remove \"{$this->label($permission)}\": no other active supervisor of this department has it, and the department has {$unfinished} unfinished ticket(s).",
                ]);
            }
        }
    }

    /**
     * A crew with tickets still to start or report on must keep an active member who can do it.
     */
    private function guardCrewWorkflow(User $user, array $removed): void
    {
        if (! $user->hasRole('field_personnel') || ! $user->is_active) {
            return;
        }

        $workflow = array_intersect($removed, PermissionCatalog::FIELD_WORKFLOW);
        if (! $workflow) {
            return;
        }

        $busyTeams = Ticket::query()
            ->whereIn('team_id', $user->teams()->pluck('teams.id'))
            ->whereIn('status', self::TEAM_UNFINISHED)
            ->distinct()
            ->pluck('team_id');

        foreach ($busyTeams as $teamId) {
            foreach ($workflow as $permission) {
                $othersHoldIt = User::query()
                    ->where('id', '!=', $user->id)
                    ->where('is_active', true)
                    ->whereHas('teams', fn ($q) => $q->where('teams.id', $teamId))
                    ->whereHas('permissions', fn ($q) => $q->where('name', $permission))
                    ->lockForUpdate()
                    ->exists();

                if (! $othersHoldIt) {
                    throw ValidationException::withMessages([
                        'permissions' => "Cannot remove \"{$this->label($permission)}\": this crew member is the only active member of their team who has it, and the team has tickets that are assigned or in progress.",
                    ]);
                }
            }
        }
    }

    private function label(string $permission): string
    {
        return config("permissions.{$permission}.label", $permission);
    }
}
