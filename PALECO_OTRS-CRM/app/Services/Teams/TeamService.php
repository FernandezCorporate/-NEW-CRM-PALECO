<?php

namespace App\Services\Teams;

use App\Enums\TicketStatus;
use App\Models\Department;
use App\Models\Team;
use App\Models\TeamRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Service encapsulating query aggregators and web/mobile roster data for Teams.
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
            ->filterByStatus($filters['status'] ?? null)
            ->sort($filters['sort'] ?? null);

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

        $personnel = $this->activeFieldPersonnel();

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

        $query->search($params['search'] ?? null)
            ->filterByStatus($params['filter'] ?? 'active')
            ->sort($params['sort'] ?? null);

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
        $personnel = $this->activeFieldPersonnel();

        $memberRoles = TeamRole::query()
            ->orderBy('role_name')
            ->select(['id', 'role_name'])
            ->get();

        return compact('personnel', 'memberRoles');
    }

    // --- PRIVATE HELPER METHODS ---

    /**
     * Retrieves active field personnel sorted alphabetically by first then last name.
     * Names are encrypted at rest, so sorting happens in memory instead of SQL ORDER BY.
     */
    private function activeFieldPersonnel(): Collection
    {
        return User::query()
            ->whereHas('role', fn ($q) => $q->where('slug_identifier', 'field_personnel'))
            ->where('is_active', true)
            ->select(['id', 'first_name', 'middle_name', 'last_name', 'name_ext'])
            ->get()
            ->sortBy(fn (User $user) => mb_strtolower($user->first_name.' '.$user->last_name))
            ->values();
    }
}
