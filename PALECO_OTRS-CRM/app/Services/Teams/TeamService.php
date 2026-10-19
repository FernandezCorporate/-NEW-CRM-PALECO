<?php

namespace App\Services\Teams;

use App\Actions\Teams\ArchiveTeam;
use App\Actions\Teams\CreateTeam;
use App\Actions\Teams\ForceDeleteTeam;
use App\Actions\Teams\RestoreTeam;
use App\Actions\Teams\UpdateTeam;
use App\Actions\Teams\ValidateMemberShiftOverlaps;
use App\Enums\TicketStatus;
use App\Models\Department;
use App\Models\Team;
use App\Models\TeamRole;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/**
 * Service encapsulating query aggregators and web/mobile roster data for Teams.
 * Delegates mutations to dedicated Action classes:
 * - CreateTeam
 * - UpdateTeam
 * - ArchiveTeam
 * - RestoreTeam
 * - ForceDeleteTeam
 * - ValidateMemberShiftOverlaps
 */
class TeamService
{
    public function __construct(
        protected CreateTeam $createTeamAction,
        protected UpdateTeam $updateTeamAction,
        protected ArchiveTeam $archiveTeamAction,
        protected RestoreTeam $restoreTeamAction,
        protected ForceDeleteTeam $forceDeleteTeamAction,
        protected ValidateMemberShiftOverlaps $validateShiftOverlapsAction
    ) {}

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
     * Checks if two 24-hour time ranges overlap, gracefully handling graveyard / cross-midnight shifts.
     */
    public static function doShiftsOverlap(string $startA, string $endA, string $startB, string $endB): bool
    {
        return ValidateMemberShiftOverlaps::doShiftsOverlap($startA, $endA, $startB, $endB);
    }

    /**
     * Validates that none of the proposed members have overlapping shifts in other active teams.
     *
     * @throws ValidationException
     */
    public function validateMemberShiftOverlaps(array $assignedMembers, string $shiftStart, string $shiftEnd, ?string $ignoreTeamId = null): void
    {
        $this->validateShiftOverlapsAction->execute($assignedMembers, $shiftStart, $shiftEnd, $ignoreTeamId);
    }

    /**
     * Creates a new operational team and synchronizes initial roster assignments.
     */
    public function createTeam(array $teamDetails, array $assignedMembers): Team
    {
        return $this->createTeamAction->execute($teamDetails, $assignedMembers);
    }

    /**
     * Updates an existing team and dynamically syncs roster changes using optimistic concurrency control.
     */
    public function updateTeam(Team $team, array $teamDetails, array $assignedMembers): array
    {
        return $this->updateTeamAction->execute($team, $teamDetails, $assignedMembers);
    }

    /**
     * Soft deletes (archives) an operational team if no active service tickets are assigned.
     */
    public function archiveTeam(Team $team): array
    {
        return $this->archiveTeamAction->execute($team);
    }

    /**
     * Restores an archived team if no active team within the department shares the same name.
     */
    public function restoreTeam(Team $team): array
    {
        return $this->restoreTeamAction->execute($team);
    }

    /**
     * Permanently deletes a trashed team ensuring no ticket relationship integrity is compromised.
     */
    public function forceDeleteTeam(Team $team): array
    {
        return $this->forceDeleteTeamAction->execute($team);
    }
}
