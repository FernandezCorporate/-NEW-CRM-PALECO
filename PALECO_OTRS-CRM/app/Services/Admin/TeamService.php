<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;

use App\Models\Department;
use App\Models\Team;
use App\Models\TeamRole;
use App\Models\User;

class TeamService
{
    public function getDashboardTeams(array $filters): array
    {
        $departments = Department::whereNull('deleted_at')->orderBy('dept_name')->pluck('dept_name', 'id');

        $query = Team::with('department')->withCount('members', 'ticket')
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
        $members = $team->members()
            ->withPivot('team_role_id', 'created_at')
            ->paginate(5, ['*'], 'page_members')
            ->withQueryString();
            
        $teamRoles = TeamRole::pluck('role_name', 'id');

        $members->getCollection()->transform(function ($member) use ($teamRoles) {
            $member->assigned_role_name = $teamRoles[$member->pivot->team_role_id] ?? 'Unknown Role';
            return $member;
        });

        $assignedTickets = $team->ticket()
            ->latest('reported_at')
            ->paginate(5, ['*'], 'page_tickets')
            ->withQueryString();

        return compact('members', 'assignedTickets');
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

    public function updateTeam(Team $team, array $teamDetails, array $assignedMembers): array
    {
        $originalUpdatedAt = $teamDetails['original_updated_at'];
        unset($teamDetails['original_updated_at']);

        return DB::transaction(function () use ($team, $teamDetails, $assignedMembers, $originalUpdatedAt) {
            $lockedTeam = Team::where('id', $team->id)->lockForUpdate()->first();

            if ((string) $lockedTeam->updated_at !== $originalUpdatedAt) {
                return ['success' => false, 'message' => 'Conflict: This team was modified by another user while you were editing.'];
            }
            
            $oldRoster = $lockedTeam->members()->get()->mapWithKeys(function ($m) {
                return [$m->id => (int) $m->pivot->team_role_id];
            })->toArray();
            
            $newRoster = collect($assignedMembers)->mapWithKeys(function ($member) {
                return [$member['user_id'] => (int) $member['team_role_id']];
            })->toArray();

            ksort($oldRoster);
            ksort($newRoster);
            
            $membersChanged = ($oldRoster !== $newRoster);

            $lockedTeam->fill($teamDetails);
            $isTeamDirty = $lockedTeam->isDirty();

            if ($isTeamDirty) {
                $lockedTeam->save(); 
            } elseif ($membersChanged) {
                $lockedTeam->touch(); 
            }

            if ($membersChanged) {
                $formattedMembers = collect($assignedMembers)->mapWithKeys(function ($member) {
                    return [$member['user_id'] => ['team_role_id' => $member['team_role_id']]];
                });

                $lockedTeam->members()->sync($formattedMembers);
                
                activity()
                    ->useLog('Teams')
                    ->performedOn($lockedTeam)
                    ->event('roster_updated')
                    ->withProperties([
                        'old' => ['member_ids' => array_keys($oldRoster)], 
                        'attributes' => ['member_ids' => array_keys($newRoster)]
                    ])
                    ->log("{$lockedTeam->team_name} roster has been modified");
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
        $team = Team::withTrashed()->find($id); 
        
        if (!$team) {
            return ['success' => false, 'message' => 'Team no longer exists. It may have been permanently deleted.'];
        }

        if (!$team->trashed()) {
            return ['success' => false, 'message' => 'Team is already active.'];
        }

        if (Team::where('team_name', $team->team_name)->exists()) {
            return ['success' => false, 'message' => 'Cannot restore team. An active team with the same name already exists.'];
        }

        $team->restore();
        return ['success' => true, 'message' => 'Team restored successfully.'];
    }

    public function forceDeleteTeam(string $id): array
    {
        $team = Team::withTrashed()->find($id);
        
        if (!$team) {
            return ['success' => true, 'message' => 'Team has already been permanently deleted.'];
        }

        if (!$team->trashed()) {
            return ['success' => false, 'message' => 'Cannot permanently delete this team because it was recently restored.'];
        }
        
        if ($team->ticket()->exists()) {
            return ['success' => false, 'message' => "Cannot permanently delete {$team->team_name} because it is currently assigned to existing service tickets."];
        }
        
        $team->forceDelete();
        
        return ['success' => true, 'message' => 'Team permanently deleted.'];
    }
}