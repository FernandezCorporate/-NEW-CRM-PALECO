<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

/*
 * Defines role-based access controls for operations on operational Teams across web and mobile surfaces.
 */
class TeamPolicy
{
    // --- WEB ADMINISTRATOR PERMISSIONS ---

    /*
     * Determine whether the user can browse all system teams.
     */
    public function viewAny(User $user): bool
    {
        return $user->role->slug_identifier === 'admin';
    }

    /*
     * Determine whether the user can view a specific team's details.
     */
    public function view(User $user): bool
    {
        return $user->role->slug_identifier === 'admin';
    }

    /*
     * Determine whether the user can access team creation or edit forms.
     */
    public function teamForm(User $user): bool
    {
        return $user->role->slug_identifier === 'admin';
    }

    /*
     * Determine whether the user can create a new team.
     */
    public function create(User $user): bool
    {
        return in_array($user->role->slug_identifier, ['admin', 'supervisor'], true);
    }

    /*
     * Determine whether the user can update an existing team via the web interface.
     */
    public function update(User $user): bool
    {
        return $user->role->slug_identifier === 'admin';
    }

    /*
     * Determine whether the user can view the delete/archive confirmation prompt.
     */
    public function deleteConfirm(User $user): bool
    {
        return $user->role->slug_identifier === 'admin';
    }

    /*
     * Determine whether the user can archive a team via the web interface.
     */
    public function archive(User $user): bool
    {
        return $user->role->slug_identifier === 'admin';
    }

    /*
     * Determine whether the user can restore an archived team via the web interface.
     */
    public function restore(User $user): bool
    {
        return $user->role->slug_identifier === 'admin';
    }

    /*
     * Determine whether the user can permanently delete a team via the web interface.
     */
    public function forceDelete(User $user): bool
    {
        return $user->role->slug_identifier === 'admin';
    }

    // --- MOBILE SUPERVISOR PERMISSIONS ---

    /*
     * Determine whether the user can browse teams belonging to their department.
     */
    public function viewAnyDepartmentTeams(User $user): bool
    {
        return $user->role->slug_identifier === 'supervisor';
    }

    /*
     * Determine whether the user can inspect a team within their department.
     */
    public function viewDepartmentTeams(User $user, Team $team): bool
    {
        return $user->role->slug_identifier === 'supervisor'
            && $user->department_id === $team->department_id;
    }

    /*
     * Determine whether the supervisor can update a team within their department.
     */
    public function mobileUpdateTeam(User $user, Team $team): bool
    {
        return $user->role->slug_identifier === 'supervisor'
            && $user->department_id === $team->department_id;
    }

    /*
     * Determine whether the supervisor can archive a team within their department.
     */
    public function mobileArchiveTeam(User $user, Team $team): bool
    {
        return $user->role->slug_identifier === 'supervisor'
            && $user->department_id === $team->department_id;
    }

    /*
     * Determine whether the supervisor can restore an archived team within their department.
     */
    public function mobileRestoreTeam(User $user, Team $team): bool
    {
        return $user->role->slug_identifier === 'supervisor'
            && $user->department_id === $team->department_id;
    }

    /*
     * Determine whether the supervisor can permanently purge a team within their department.
     */
    public function mobileDestroyTeam(User $user, Team $team): bool
    {
        return $user->role->slug_identifier === 'supervisor'
            && $user->department_id === $team->department_id;
    }

    /*
     * Determine whether the user can retrieve candidate personnel and roles for team creation.
     */
    public function mobileTeamOptions(User $user): bool
    {
        return $user->role->slug_identifier === 'supervisor';
    }
}
