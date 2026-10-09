<?php

namespace App\Services\Teams;

use App\Enums\TicketStatus;
use App\Models\Department;
use App\Models\Team;
use App\Models\TeamRole;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Encapsulates backend business logic and roster syncing for operational Teams
 * across both the Administrative Web Portal and Mobile Supervisor API.
 */
class TeamService
{
    // --- WEB QUERY METHODS ---

    /**
     * Retrieves paginated teams with member counts, assigned tickets, and department metadata for Web Admin.
     */
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

    /**
     * Retrieves detailed team information, paginated roster with role titles, and assigned tickets for Web Admin.
     */
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

    /**
     * Retrieves department listings, active field personnel, and team roles to populate Web team forms.
     */
    public function getFormData(): array
    {
        $depts = Department::orderBy('dept_name')->pluck('dept_name', 'id');

        $personnel = User::query()
            ->whereHas('role', fn ($q) => $q->where('slug_identifier', 'field_personnel'))
            ->where('is_active', true)
            ->orderBy('first_name', 'asc')
            ->select(['id', 'first_name', 'middle_name', 'last_name', 'name_ext'])
            ->get();

        $memberRoles = TeamRole::orderBy('role_name')->get();

        return compact('depts', 'personnel', 'memberRoles');
    }

    // --- MOBILE QUERY METHODS ---

    /**
     * Retrieves paginated teams belonging to the supervisor's department with ticket workload counts for Mobile API.
     */
    public function deptTeamList(User $user, array $params): LengthAwarePaginator
    {
        $query = Team::query()
            ->withTrashed()
            ->where('department_id', $user->department_id)
            ->withCount([
                'members',
                'ticket as tickets_total',
                'ticket as tickets_open' => fn ($q) => $q->where('status', TicketStatus::OPEN),
                'ticket as tickets_assigned' => fn ($q) => $q->where('status', TicketStatus::ASSIGNED),
                'ticket as tickets_in_progress' => fn ($q) => $q->where('status', TicketStatus::IN_PROGRESS),
                'ticket as tickets_resolved' => fn ($q) => $q->where('status', TicketStatus::RESOLVED),
                'ticket as tickets_closed' => fn ($q) => $q->where('status', TicketStatus::CLOSED),
            ])
            ->with(['members' => function ($query) {
                $query->select('users.id', 'users.first_name', 'users.middle_name', 'users.last_name', 'users.name_ext');
            }]);

        $query->apiSearch($params['search'] ?? null)
            ->apiFilterStatus($params['filter'] ?? 'active')
            ->apiSort($params['sort'] ?? null);

        return $query->paginate(10)->withQueryString();
    }

    /**
     * Retrieves a single team's details with workload statistics and populated roster for Mobile API.
     */
    public function deptTeamDetails(User $user, Team $team): Team
    {
        $team->loadCount([
            'members',
            'ticket as tickets_total',
            'ticket as tickets_open' => fn ($q) => $q->where('status', TicketStatus::OPEN),
            'ticket as tickets_assigned' => fn ($q) => $q->where('status', TicketStatus::ASSIGNED),
            'ticket as tickets_in_progress' => fn ($q) => $q->where('status', TicketStatus::IN_PROGRESS),
            'ticket as tickets_resolved' => fn ($q) => $q->where('status', TicketStatus::RESOLVED),
            'ticket as tickets_closed' => fn ($q) => $q->where('status', TicketStatus::CLOSED),
        ]);

        $team->load(['members' => function ($query) {
            $query->select('users.id', 'users.first_name', 'users.middle_name', 'users.last_name', 'users.name_ext');
        }]);

        return $team;
    }

    /**
     * Retrieves active field personnel and available team roles to populate mobile creation forms.
     */
    public function getFormOptions(): array
    {
        $personnel = User::query()
            ->whereHas('role', fn ($q) => $q->where('slug_identifier', 'field_personnel'))
            ->where('is_active', true)
            ->orderBy('first_name', 'asc')
            ->select(['id', 'first_name', 'middle_name', 'last_name', 'name_ext'])
            ->get();

        $memberRoles = TeamRole::query()
            ->orderBy('role_name')
            ->select(['id', 'role_name'])
            ->get();

        return compact('personnel', 'memberRoles');
    }

    // --- MUTATING METHODS ---

    /**
     * Creates a new operational team and synchronizes initial roster assignments.
     */
    public function createTeam(array $teamDetails, array $assignedMembers): Team
    {
        return DB::transaction(function () use ($teamDetails, $assignedMembers) {
            $team = Team::create($teamDetails);

            if (! empty($assignedMembers)) {
                $formattedMembers = collect($assignedMembers)->mapWithKeys(function ($member) {
                    return [$member['user_id'] => ['team_role_id' => $member['team_role_id']]];
                });

                $team->members()->sync($formattedMembers);
            }

            return $team->fresh(['members' => function ($query) {
                $query->select('users.id', 'users.first_name', 'users.middle_name', 'users.last_name', 'users.name_ext');
            }]);
        });
    }

    /**
     * Updates an existing team and dynamically syncs roster changes using optimistic concurrency control.
     */
    public function updateTeam(Team $team, array $teamDetails, array $assignedMembers): array
    {
        if ($team->trashed()) {
            return [
                'success' => false,
                'message' => 'Conflict: This team has been archived by an administrator and can no longer be modified.',
            ];
        }

        $originalUpdatedAt = $teamDetails['original_updated_at'];
        unset($teamDetails['original_updated_at']);

        return DB::transaction(function () use ($team, $teamDetails, $assignedMembers, $originalUpdatedAt) {
            $lockedTeam = Team::where('id', $team->id)->lockForUpdate()->first();

            // Safe Carbon-based optimistic concurrency timestamp check
            if (! $lockedTeam->updated_at->eq(Carbon::parse($originalUpdatedAt))) {
                return [
                    'success' => false,
                    'message' => 'Conflict: This team was modified by another user while you were editing.',
                ];
            }

            // Compare roster states to detect membership changes
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
                        'attributes' => ['member_ids' => array_keys($newRoster)],
                    ])
                    ->log("{$lockedTeam->team_name} roster has been modified");
            }

            $freshTeam = $lockedTeam->fresh(['members' => function ($query) {
                $query->select('users.id', 'users.first_name', 'users.middle_name', 'users.last_name', 'users.name_ext');
            }]);

            return [
                'success' => true,
                'changed' => $isTeamDirty || $membersChanged,
                'team' => $freshTeam,
            ];
        });
    }

    // --- DESTRUCTIVE & STATE METHODS ---

    /**
     * Soft deletes (archives) an operational team if no active service tickets are assigned.
     */
    public function archiveTeam(Team $team): array
    {
        if ($team->trashed()) {
            return [
                'success' => false,
                'message' => 'Conflict: This team has already been archived by an administrator.',
            ];
        }

        $hasActiveTickets = $team->ticket()->exists();

        if ($hasActiveTickets) {
            return [
                'success' => false,
                'message' => 'Cannot archive team. They currently have active assigned service tickets.',
            ];
        }

        $team->delete();

        return [
            'success' => true,
            'message' => 'Team archived successfully.',
            'team' => $team,
        ];
    }

    /**
     * Restores an archived team if no active team within the department shares the same name.
     */
    public function restoreTeam(Team $team): array
    {
        if (! $team->trashed()) {
            return [
                'success' => false,
                'message' => 'This team is already active and cannot be restored.',
            ];
        }

        if (Team::where('team_name', $team->team_name)
            ->where('department_id', $team->department_id)
            ->exists()) {
            return [
                'success' => false,
                'message' => 'Cannot restore team. A team with the same name already exists within this department.',
            ];
        }

        $team->restore();

        return [
            'success' => true,
            'message' => 'Team restored successfully.',
            'team' => $team,
        ];
    }

    /**
     * Permanently deletes a trashed team ensuring no ticket relationship integrity is compromised.
     */
    public function forceDeleteTeam(Team $team): array
    {
        if (! $team->trashed()) {
            return [
                'success' => false,
                'message' => 'Cannot permanently delete an active team. Please archive it first.',
            ];
        }

        if ($team->ticket()->exists()) {
            return [
                'success' => false,
                'message' => "Cannot permanently delete {$team->team_name} because it is currently assigned to existing service tickets.",
            ];
        }

        $team->forceDelete();

        return [
            'success' => true,
            'message' => 'Team permanently deleted successfully.',
        ];
    }
}
