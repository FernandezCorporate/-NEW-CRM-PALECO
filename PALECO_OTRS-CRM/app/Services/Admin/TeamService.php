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

    public function getTeamDetails(Team $team): array
    {
        $members = $team->members()->withPivot('team_role_id', 'created_at')->paginate(5);
        $teamRoles = TeamRole::pluck('role_name', 'id');

        return compact('members', 'teamRoles');
    }

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
     * Uses strict array comparisons to prevent false positive updates caused by Laravel timestamps.
     */
    public function updateTeam(Team $team, array $teamDetails, array $assignedMembers): array
    {
        if ((string) $team->updated_at !== $teamDetails['original_updated_at']) {
            return ['success' => false, 'message' => 'Conflict: This team was modified by another user while you were editing.'];
        }
        unset($teamDetails['original_updated_at']);

        return DB::transaction(function () use ($team, $teamDetails, $assignedMembers) {
            
            // 1. Snapshot the exact state of the old roster mapped as [user_id => role_id]
            $oldRoster = $team->members()->get()->mapWithKeys(function ($m) {
                return [$m->id => (int) $m->pivot->team_role_id];
            })->toArray();
            
            // 2. Format the incoming array identically for a strict comparison
            $newRoster = collect($assignedMembers)->mapWithKeys(function ($member) {
                return [$member['user_id'] => (int) $member['team_role_id']];
            })->toArray();

            // 3. Sort by keys to ensure identical order before comparing
            ksort($oldRoster);
            ksort($newRoster);
            
            // Strict PHP evaluation guarantees we only log changes if data actually changed
            $membersChanged = ($oldRoster !== $newRoster);

            $team->fill($teamDetails);
            $isTeamDirty = $team->isDirty();
            $team->save();

            if ($membersChanged) {
                $formattedMembers = collect($assignedMembers)->mapWithKeys(function ($member) {
                    return [$member['user_id'] => ['team_role_id' => $member['team_role_id']]];
                });

                $team->members()->sync($formattedMembers);
                
                activity()
                    ->useLog('Teams')
                    ->performedOn($team)
                    ->event('roster_updated')
                    ->withProperties([
                        'old' => ['member_ids' => array_keys($oldRoster)], 
                        'attributes' => ['member_ids' => array_keys($newRoster)]
                    ])
                    ->log("{$team->team_name} roster has been modified");
            }
            
            return ['success' => true, 'changed' => $isTeamDirty || $membersChanged];
        });
    }

    public function archiveTeam(Team $team): array
    {
        $hasActiveTickets = $team->ticket()->exists();
        
        if ($hasActiveTickets) {
            return ['success' => false, 'message' => 'Cannot archive team. They currently have active assigned service tickets.'];
        }
        
        $team->delete();
        return ['success' => true, 'message' => 'Team archived successfully.'];
    }

    public function restoreTeam(string $id): array
    {
        $team = Team::onlyTrashed()->findOrFail($id); 

        if (Team::where('team_name', $team->team_name)->exists()) {
            return ['success' => false, 'message' => 'Cannot restore team. An active team with the same name already exists.'];
        }

        $team->restore();
        return ['success' => true, 'message' => 'Team restored successfully.'];
    }

    public function forceDeleteTeam(string $id): array
    {
        $team = Team::onlyTrashed()->findOrFail($id);
        
        if ($team->ticket()->exists()) {
            return [
                'success' => false, 
                'message' => "Cannot permanently delete {$team->team_name} because it is currently assigned to existing service tickets. You may only keep it archived."
            ];
        }
        
        $team->forceDelete();
        
        return [
            'success' => true, 
            'message' => 'Team permanently deleted.'
        ];
    }
}