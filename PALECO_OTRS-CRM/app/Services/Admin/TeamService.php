<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;

use App\Models\Department;
use App\Models\Team;
use App\Models\TeamRole;
use App\Models\User;

/*
 * Encapsulates the business logic for managing operational Teams.
 * Handles complex relational syncing and roster transaction logging.
 */
class TeamService
{
    // --- VIEW DATA AGGREGATION ---

    /*
     * Retrieves a paginated list of teams alongside active department choices.
     * Applies search, filter, and sort scopes directly to the query.
     */
    public function getDashboardTeams(array $filters): array
    {
        $departments = Department::whereNull('deleted_at')->orderBy('dept_name')->pluck('dept_name', 'id');

        $query = Team::with('department')->withCount('members')
            ->search($filters['search'] ?? null)
            ->filter($filters['filter'] ?? null)
            ->sort($filters['sort'] ?? null);

        if (($filters['status'] ?? null) === 'archived') {
            $query->onlyTrashed();
        }

        $teams = $query->paginate(9)->withQueryString();

        return compact('teams', 'departments');
    }

    /*
     * Compiles detailed records for a specific team's profile.
     * Includes a paginated list of attached members and available team roles.
     */
    public function getTeamDetails(Team $team): array
    {
        $members = $team->members()->withPivot('team_role_id', 'created_at')->paginate(5);
        $teamRoles = TeamRole::pluck('role_name', 'id');

        return compact('members', 'teamRoles');
    }

    /*
     * Gathers all required relational data (departments, personnel, roles) to populate the Team form.
     */
    public function getFormData(): array
    {
        $depts = Department::orderBy('dept_name')->pluck('dept_name', 'id');
        
        $personnel = User::query()
            ->whereHas('role', fn($q) => $q->where('slug_identifier', 'field_personnel'))
            ->where('is_active', true)
            ->orderBy('first_name', 'asc')
            ->select(['id', 'first_name', 'middle_name', 'last_name', 'name_ext'])
            ->get();
            
        $memberRoles = TeamRole::orderBy('role_name')->get();

        return compact('depts', 'personnel', 'memberRoles');
    }

    // --- MUTATING & STATE METHODS ---

    /*
     * Creates a new team and syncs the assigned members within an atomic database transaction.
     */
    public function createTeam(array $teamDetails, array $assignedMembers): void
    {
        DB::transaction(function () use ($teamDetails, $assignedMembers) {
            $team = Team::create($teamDetails);

            if (!empty($assignedMembers)) {
                $formattedMembers = collect($assignedMembers)->mapWithKeys(function ($member) {
                    return [$member['user_id'] => ['team_role_id' => $member['team_role_id']]];
                });
                $team->members()->sync($formattedMembers);
            }
        });
    }

    /*
     * Updates an existing team and synchronizes its roster within a transaction.
     * Automatically logs an activity event if the membership list is modified.
     * Returns true if any data or member assignments were actually changed.
     */
    public function updateTeam(Team $team, array $teamDetails, array $assignedMembers): bool
    {
        return DB::transaction(function () use ($team, $teamDetails, $assignedMembers) {
            $oldMemberIds = $team->members()->pluck('users.id')->toArray();

            $team->fill($teamDetails);
            $isTeamDirty = $team->isDirty();
            $team->save();

            $formattedMembers = collect($assignedMembers)->mapWithKeys(function ($member) {
                return [$member['user_id'] => ['team_role_id' => $member['team_role_id']]];
            });

            $changes = $team->members()->sync($formattedMembers);
            $newMemberIds = array_keys($formattedMembers->toArray());
            
            $membersChanged = !empty($changes['attached']) || !empty($changes['detached']) || !empty($changes['updated']);
            
            if ($membersChanged) {
                activity()
                    ->useLog('Teams')
                    ->performedOn($team)
                    ->event('roster_updated')
                    ->withProperties([
                        'old' => ['member_ids' => $oldMemberIds],
                        'attributes' => ['member_ids' => $newMemberIds]
                    ])
                    ->log("{$team->team_name} roster has been modified");
            }
            
            // 3. Return the boolean state back to the controller
            return $isTeamDirty || $membersChanged;
        });
    }

    /*
     * Attempts to restore a soft-deleted team.
     * Enforces uniqueness checks against active teams sharing the same name.
     */
    public function restoreTeam(int $id): array
    {
        $team = Team::onlyTrashed()->findOrFail($id); 

        if (Team::where('team_name', $team->team_name)->exists()) {
            return ['success' => false, 'message' => 'Cannot restore team. An active team with the same name already exists.'];
        }

        $team->restore();
        return ['success' => true, 'message' => 'Team restored successfully.'];
    }
}