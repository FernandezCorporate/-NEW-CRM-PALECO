<?php

namespace App\Services\Api\Teams;

use App\Enums\TicketStatus;
use App\Models\Team;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator; // <-- Import the paginator

/*
 * Encapsulates the core team retrieval logic for the mobile API.
 * Ensures foremen can only query teams within their specific department.
 */
class TeamService
{
    /*
     * Retrieves all active teams for a Foreman's department.
     * Eager loads member counts and workload statistics in a single optimized pass.
     * Returns a paginated result to construct the API response links.
     */
    public function getDepartmentTeams(User $user, array $params): LengthAwarePaginator // <-- Update return type
    {
        $query = Team::query()
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

        $query->search($params['search'] ?? null)
              ->sort($params['sort'] ?? null);

        // Swap get() for paginate()
        return $query->paginate(10); 
    }
}