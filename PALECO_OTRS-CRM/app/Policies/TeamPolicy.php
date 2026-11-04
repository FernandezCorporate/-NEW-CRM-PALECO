<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\Response;

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
        return $user->hasRole('admin') && $user->hasPermission('teams.view');
    }

    /*
     * Determine whether the user can view a specific team's details.
     */
    public function view(User $user): bool
    {
        return $user->hasRole('admin') && $user->hasPermission('teams.view');
    }

    /*
     * Determine whether the user can access team creation or edit forms.
     */
    public function teamForm(User $user): bool
    {
        return $user->hasRole('admin') && ($user->hasPermission('teams.create') || $user->hasPermission('teams.update'));
    }

    /*
     * Determine whether the user can create a new team.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin', 'supervisor') && $user->hasPermission('teams.create');
    }

    /*
     * Determine whether the user can update an existing team via the web interface.
     */
    public function update(User $user): bool
    {
        return $user->hasRole('admin') && $user->hasPermission('teams.update');
    }

    /*
     * Determine whether the user can view the delete/archive confirmation prompt.
     */
    public function deleteConfirm(User $user): bool
    {
        // The same page confirms archiving and permanent deletion
        return $user->hasRole('admin') && ($user->hasPermission('teams.archive') || $user->hasPermission('teams.delete'));
    }

    /*
     * Determine whether the user can archive a team via the web interface.
     */
    public function archive(User $user): bool
    {
        return $user->hasRole('admin') && $user->hasPermission('teams.archive');
    }

    /*
     * Determine whether the user can restore an archived team via the web interface.
     */
    public function restore(User $user): bool
    {
        return $user->hasRole('admin') && $user->hasPermission('teams.archive');
    }

    /*
     * Determine whether the user can permanently delete a team via the web interface.
     */
    public function forceDelete(User $user): bool
    {
        return $user->hasRole('admin') && $user->hasPermission('teams.delete');
    }

    // --- MOBILE SUPERVISOR PERMISSIONS ---

    /*
     * Determine whether the user can browse teams belonging to their department.
     */
    public function viewAnyDepartmentTeams(User $user): bool
    {
        return $user->hasRole('supervisor') && $user->hasPermission('teams.view');
    }

    /*
     * Determine whether the user can inspect a team within their department.
     */
    public function viewDepartmentTeams(User $user, Team $team): Response
    {
        if (! $user->hasRole('supervisor')) {
            return Response::deny('Only supervisors can access department teams.');
        }

        if (! $user->hasPermission('teams.view')) {
            return Response::deny('You do not have permission to access department teams.');
        }

        return $user->department_id === $team->department_id
            ? Response::allow()
            : Response::denyAsNotFound('The requested team was not found.');
    }

    /*
     * Determine whether the supervisor can update a team within their department.
     */
    public function mobileUpdateTeam(User $user, Team $team): Response
    {
        if (! $user->hasRole('supervisor')) {
            return Response::deny('Only supervisors can update department teams.');
        }

        if (! $user->hasPermission('teams.update')) {
            return Response::deny('You do not have permission to update department teams.');
        }

        return $user->department_id === $team->department_id
            ? Response::allow()
            : Response::denyAsNotFound('The requested team was not found.');
    }

    /*
     * Determine whether the supervisor can archive a team within their department.
     */
    public function mobileArchiveTeam(User $user, Team $team): Response
    {
        if (! $user->hasRole('supervisor')) {
            return Response::deny('Only supervisors can archive department teams.');
        }

        if (! $user->hasPermission('teams.archive')) {
            return Response::deny('You do not have permission to archive department teams.');
        }

        return $user->department_id === $team->department_id
            ? Response::allow()
            : Response::denyAsNotFound('The requested team was not found.');
    }

    /*
     * Determine whether the supervisor can restore an archived team within their department.
     */
    public function mobileRestoreTeam(User $user, Team $team): Response
    {
        if (! $user->hasRole('supervisor')) {
            return Response::deny('Only supervisors can restore department teams.');
        }

        if (! $user->hasPermission('teams.archive')) {
            return Response::deny('You do not have permission to restore department teams.');
        }

        return $user->department_id === $team->department_id
            ? Response::allow()
            : Response::denyAsNotFound('The requested team was not found.');
    }

    /*
     * Determine whether the supervisor can permanently purge a team within their department.
     */
    public function mobileDestroyTeam(User $user, Team $team): Response
    {
        if (! $user->hasRole('supervisor')) {
            return Response::deny('Only supervisors can delete department teams.');
        }

        if (! $user->hasPermission('teams.delete')) {
            return Response::deny('You do not have permission to delete department teams.');
        }

        return $user->department_id === $team->department_id
            ? Response::allow()
            : Response::denyAsNotFound('The requested team was not found.');
    }

    /*
     * Determine whether the user can retrieve candidate personnel and roles for team creation.
     */
    public function mobileTeamOptions(User $user): bool
    {
        return $user->hasRole('supervisor') && ($user->hasPermission('teams.create') || $user->hasPermission('teams.update'));
    }
}
