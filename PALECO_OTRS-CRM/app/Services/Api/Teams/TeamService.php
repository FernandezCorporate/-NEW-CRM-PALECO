<?php

namespace App\Services\Api\Teams;

use Illuminate\Support\Facades\DB;
use App\Enums\TicketStatus;
use App\Models\Team;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator; 

/*
 * Encapsulates the core team retrieval and creation logic for the mobile API.
 * Ensures foremen can only query or build teams within their specific department.
 */
class TeamService
{
    public function getDepartmentTeams(User $user, array $params): LengthAwarePaginator 
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

        return $query->paginate(10); 
    }

    /*
     * Retrieves a single team's details for a Foreman's department.
     * Works seamlessly for both active and archived teams.
     */
    public function getDepartmentTeam(User $user, Team $team): Team
    {
        if ($team->department_id !== $user->department_id) {
            abort(403, 'Access Denied: You cannot view teams outside your assigned department.');
        }

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
    public function createTeam(array $teamDetails, array $assignedMembers): void
    {
        DB::transaction(function () use ($teamDetails, $assignedMembers) {
            
            // 1. Insert the core team details into the database
            $team = Team::create($teamDetails);

            // 2. Format and attach the initial members to the pivot table
            if (!empty($assignedMembers)) {
                $formattedMembers = collect($assignedMembers)->mapWithKeys(function ($member) {
                    return [$member['user_id'] => ['team_role_id' => $member['team_role_id']]];
                });
                
                $team->members()->sync($formattedMembers);
            }
        });
    }
}