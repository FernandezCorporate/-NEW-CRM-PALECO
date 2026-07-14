<?php

namespace App\Services\Admin;

use App\Models\Team;
use App\Models\Department;
use App\Models\User;
use App\Models\TeamRole;
use Illuminate\Support\Facades\DB;

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

    public function updateTeam(Team $team, array $teamDetails, array $assignedMembers): void
    {
        DB::transaction(function () use ($team, $teamDetails, $assignedMembers) {
            $oldMemberIds = $team->members()->pluck('users.id')->toArray();

            $team->update($teamDetails);

            $formattedMembers = collect($assignedMembers)->mapWithKeys(function ($member) {
                return [$member['user_id'] => ['team_role_id' => $member['team_role_id']]];
            });

            $changes = $team->members()->sync($formattedMembers);
            $newMemberIds = array_keys($formattedMembers->toArray());

            if (!empty($changes['attached']) || !empty($changes['detached']) || !empty($changes['updated'])) {
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
        });
    }

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