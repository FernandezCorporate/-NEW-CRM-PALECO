<?php

namespace App\Services\Api\Teams;

use App\Enums\TicketStatus;
use App\Models\Team;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator; 

/*
 * Encapsulates the core team retrieval logic for the mobile API.
 * Ensures foremen can only query teams within their specific department.
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
}