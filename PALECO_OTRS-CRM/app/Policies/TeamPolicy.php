<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    public function viewAny(User $user): bool { return $user->role->slug_identifier === 'admin'; }
    public function view(User $user): bool { return $user->role->slug_identifier === 'admin'; }
    public function teamForm(User $user): bool { return $user->role->slug_identifier === 'admin'; }
    public function create(User $user): bool { return in_array($user->role->slug_identifier, ['admin', 'foreman']); }
    public function update(User $user): bool { return $user->role->slug_identifier === 'admin'; }
    public function deleteConfirm(User $user): bool { return $user->role->slug_identifier === 'admin'; }
    public function archive(User $user): bool { return $user->role->slug_identifier === 'admin'; }
    public function delete(User $user): bool { return $user->role->slug_identifier === 'admin'; }
    public function restore(User $user): bool { return $user->role->slug_identifier === 'admin'; }
    public function forceDelete(User $user): bool { return $user->role->slug_identifier === 'admin'; }


    public function viewAnyDepartmentTeams(User $user): bool { return $user->role->slug_identifier === 'foreman'; }
    public function viewDepartmentTeams(User $user, Team $team): bool { return $user->role->slug_identifier === 'foreman' && $user->department_id === $team->department_id; }
    public function mobileUpdateTeam(User $user, Team $team): bool { return $user->role->slug_identifier === 'foreman' && $user->department_id === $team->department_id; }
    public function mobileArchiveTeam(User $user, Team $team): bool { return $user->role->slug_identifier === 'foreman' && $user->department_id === $team->department_id; }
    public function mobileRestoreTeam(User $user, Team $team): bool { return $user->role->slug_identifier === 'foreman' && $user->department_id === $team->department_id; }
    public function mobileDestroyTeam(User $user, Team $team): bool { return $user->role->slug_identifier === 'foreman' && $user->department_id === $team->department_id; }
    
    public function mobileTeamOptions(User $user): bool { return $user->role->slug_identifier === 'foreman'; }

    /*
     * viewAny: Determines if the user can view the list of teams.
     * view: Determines if the user can view a specific team's details.
     * teamForm: Determines if the user can access the team creation or edit forms.
     * create: Determines if the user can create new teams.
     * update: Determines if the user can update an existing team.
     * deleteConfirm: Determines if the user can access the deletion confirmation prompt.
     * archive: Determines if the user can soft-delete (archive) a team.
     * delete: Determines if the user can initiate a standard delete operation.
     * restore: Determines if the user can restore a previously archived team.
     * forceDelete: Determines if the user can permanently delete a team.
     */
}