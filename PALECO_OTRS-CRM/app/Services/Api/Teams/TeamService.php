<?php

namespace App\Services\Api\Teams;

use Illuminate\Support\Facades\DB;
use App\Enums\TicketStatus;
use App\Models\Team;
use App\Models\User;
use App\Models\TeamRole;
use Illuminate\Pagination\LengthAwarePaginator; 

/*
 * Encapsulates the core team retrieval and creation logic for the mobile API.
 * Ensures foremen can only query or build teams within their specific department.
 */
class TeamService
{
    public function deptTeamList(User $user, array $params): LengthAwarePaginator 
    {
        $query = Team::query()
            ->withTrashed() 
            ->where('department_id', $user->department_id)
            ->withCount([
                'members',
                'ticket as tickets_total',
                'ticket as tickets_open'        => fn($q) => $q->where('status', TicketStatus::OPEN),
                'ticket as tickets_assigned'    => fn($q) => $q->where('status', TicketStatus::ASSIGNED),
                'ticket as tickets_in_progress' => fn($q) => $q->where('status', TicketStatus::IN_PROGRESS),
                'ticket as tickets_resolved'    => fn($q) => $q->where('status', TicketStatus::RESOLVED),
                'ticket as tickets_closed'      => fn($q) => $q->where('status', TicketStatus::CLOSED),
            ])
            ->with(['members' => function ($query) {
                $query->select('users.id', 'users.first_name', 'users.middle_name', 'users.last_name', 'users.name_ext');
            }]);

        $query->apiSearch($params['search'] ?? null)
              ->apiFilterStatus($params['filter'] ?? 'active') 
              ->apiSort($params['sort'] ?? null);

        return $query->paginate(10)->withQueryString(); 
    }

    /*
     * Retrieves a single team's details for a Foreman's department.
     * Works seamlessly for both active and archived teams.
     */
    public function deptTeamDetails(User $user, Team $team): Team
    {
        $team->loadCount([
            'members',
            'ticket as tickets_total',
            'ticket as tickets_open'        => fn($q) => $q->where('status', TicketStatus::OPEN),
            'ticket as tickets_assigned'    => fn($q) => $q->where('status', TicketStatus::ASSIGNED),
            'ticket as tickets_in_progress' => fn($q) => $q->where('status', TicketStatus::IN_PROGRESS),
            'ticket as tickets_resolved'    => fn($q) => $q->where('status', TicketStatus::RESOLVED),
            'ticket as tickets_closed'      => fn($q) => $q->where('status', TicketStatus::CLOSED),
        ]);
        
        $team->load(['members' => function ($query) {
            $query->select('users.id', 'users.first_name', 'users.middle_name', 'users.last_name', 'users.name_ext');
        }]);

        return $team;
    }

    /*
     * Creates a new operational team and safely synchronizes the initial roster.
     * Wrapped in a transaction to prevent orphaned team records if roster syncing fails.
     */
    public function createTeam(array $teamDetails, array $assignedMembers): Team
    {
        return DB::transaction(function () use ($teamDetails, $assignedMembers) {
            
            // 1. Insert the core team details into the database
            $team = Team::create($teamDetails);

            // 2. Format and attach the initial members to the pivot table
            if (!empty($assignedMembers)) {
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

    /*
     * Updates an existing team and dynamically syncs roster changes.
     * Uses pessimistic locking (lockForUpdate) to prevent race conditions via the API.
     */
    public function updateTeam(Team $team, array $teamDetails, array $assignedMembers): array
    {
        $originalUpdatedAt = $teamDetails['original_updated_at'];
        unset($teamDetails['original_updated_at']);

        return DB::transaction(function () use ($team, $teamDetails, $assignedMembers, $originalUpdatedAt) {
            
            // 1. Lock the row to prevent race conditions
            $lockedTeam = Team::where('id', $team->id)->lockForUpdate()->first();

            // 2. Optimistic timestamp check
            if ((string) $lockedTeam->updated_at !== $originalUpdatedAt) {
                return ['success' => false, 'message' => 'Conflict: This team was modified by another user while you were editing.'];
            }
            
            // 3. Compare Roster Arrays to detect changes
            $oldRoster = $lockedTeam->members()->get()->mapWithKeys(function ($m) {
                return [$m->id => (int) $m->pivot->team_role_id];
            })->toArray();
            
            $newRoster = collect($assignedMembers)->mapWithKeys(function ($member) {
                return [$member['user_id'] => (int) $member['team_role_id']];
            })->toArray();

            ksort($oldRoster);
            ksort($newRoster);
            
            $membersChanged = ($oldRoster !== $newRoster);

            // 4. Update Core Details
            $lockedTeam->fill($teamDetails);
            $isTeamDirty = $lockedTeam->isDirty();

            if ($isTeamDirty) {
                $lockedTeam->save(); 
            } elseif ($membersChanged) {
                // If only roster changed, manually bump the team's timestamp for locking purposes
                $lockedTeam->touch(); 
            }

            // 5. Sync Roster if changes were detected
            if ($membersChanged) {
                $formattedMembers = collect($assignedMembers)->mapWithKeys(function ($member) {
                    return [$member['user_id'] => ['team_role_id' => $member['team_role_id']]];
                });

                $lockedTeam->members()->sync($formattedMembers);
                
                // Manually fire the roster_updated activity log to match the Web App
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
            return ['success' => false, 'message' => 'Team not found. It may have been permanently deleted or never existed.'];
        }

        if (!$team->trashed()) {
            return ['success' => false, 'message' => 'This team is already active and cannot be restored.'];
        }

        if (Team::where('team_name', $team->team_name)->exists()) {
            return ['success' => false, 'message' => 'Cannot restore team. A team with the same name already exists.'];
        }

        $team->restore();
        return ['success' => true, 'message' => 'Team restored successfully.'];
    }

    public function forceDeleteTeam(Team $team): array
    {
        // 1. State Guard
        if (!$team->trashed()) {
            return ['success' => false, 'message' => 'Cannot permanently delete an active team. Please archive it first.'];
        }

        // 2. Data Integrity Guard (Prevents orphaned tickets)
        if ($team->ticket()->exists()) {
            return ['success' => false, 'message' => "Cannot permanently delete {$team->team_name} because it is currently assigned to existing service tickets."];
        }

        // 3. Execute Deletion
        $team->forceDelete();
        
        return ['success' => true, 'message' => 'Team permanently deleted successfully.'];
    }

    /*
     * Retrieves active field personnel and available team roles for mobile form population.
     */
    public function getFormOptions(): array
    {
        // 1. Fetch active users assigned to the 'field_personnel' role
        $personnel = User::query()
            ->whereHas('role', fn($q) => $q->where('slug_identifier', 'field_personnel'))
            ->where('is_active', true)
            ->orderBy('first_name', 'asc')
            // Only select the exact columns needed by the mobile frontend to minimize payload size
            ->select(['id', 'first_name', 'middle_name', 'last_name', 'name_ext'])
            ->get();
            
        // 2. Fetch available team roles
        $memberRoles = TeamRole::query()
            ->orderBy('role_name')
            ->select(['id', 'role_name'])
            ->get();

        return compact('personnel', 'memberRoles');
    }
}