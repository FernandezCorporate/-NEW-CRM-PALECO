<?php

namespace App\Services\Tickets;

use App\Enums\ComplaintSources;
use App\Enums\TicketStatus;
use App\Models\Department;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Encapsulates ticket queries, search filters, pagination, and view-model preparation across Web and Mobile portals.
 */
class TicketQueryService
{
    // --- WEB QUERY METHODS ---

    /**
     * Retrieve paginated tickets filtered by search query, category, department, and status for CWD web portal.
     */
    public function getTicketList(Request $request): array
    {
        $tickets = Ticket::with(['category', 'department', 'creator', 'parentTicket'])
            ->search($request->search)
            ->filterByCategory($request->filter)
            ->filterByDepartment($request->department)
            ->filterByStatus($request->status === 'all' ? null : $request->status)
            ->filterByAging($request->aging, $request->status)
            ->sort($request->sort)
            ->paginate(10);

        $categories = TicketCategory::orderBy('category_name')->get();
        $departments = Department::orderBy('dept_name')->get();

        return [
            'tickets' => $tickets,
            'categories' => $categories,
            'departments' => $departments,
            'statuses' => TicketStatus::cases(),
        ];
    }

    /**
     * Eagerly loads all relationships and hierarchy details for ticket view in CWD web portal.
     */
    public function getTicketDetails(Ticket $ticket): array
    {
        $ticket->load([
            // Core & Routing
            'creator',
            'department.supervisors',
            'team.members',
            'category',
            'consumer',

            // Hierarchy
            'parentTicket.department',
            'childTickets.department',

            // History Modules
            'statusLog.updater',
            'assignments.team',
            'assignments.assigner',
            'endorsements.suggestedDepartment',
            'endorsements.creator',
            'endorsements.reviewer',
            'accomplishments.accomplishedBy',
            'accomplishments.approvedBy',
            'accomplishments.rejectedBy',

            // Remarks & Notes
            'remarks.author.role',
        ]);

        return compact('ticket');
    }

    /**
     * Load initial dataset options for ticket creation form.
     */
    public function loadTicketForm(): array
    {
        $sources = ComplaintSources::cases();
        $categories = TicketCategory::orderBy('category_name')->get();
        $departments = Department::orderBy('dept_name')->get();

        return [
            'sources' => $sources,
            'categories' => $categories,
            'departments' => $departments,
        ];
    }

    /**
     * Load initial dataset options for creating a child ticket under a parent ticket.
     */
    public function loadChildTicketForm(Ticket $parentTicket): array
    {
        $parentTicket->load(['category', 'department', 'consumer']);
        $categories = TicketCategory::orderBy('category_name')->get();
        $departments = Department::orderBy('dept_name')->get();

        return [
            'parentTicket' => $parentTicket,
            'categories' => $categories,
            'departments' => $departments,
        ];
    }

    /**
     * Load initial dataset options for editing clerical intake details of an existing ticket.
     */
    public function loadTicketEditForm(Ticket $ticket): array
    {
        $ticket->loadMissing(['category', 'department', 'consumer']);
        $sources = ComplaintSources::cases();
        $categories = TicketCategory::orderBy('category_name')->get();
        $departments = Department::orderBy('dept_name')->get();

        return compact('ticket', 'sources', 'categories', 'departments');
    }

    // --- MOBILE QUERY METHODS ---

    /**
     * Retrieves paginated inbox tickets filtered by user role and query parameters for Mobile API.
     */
    public function getInboxTickets(User $user, array $params): LengthAwarePaginator
    {
        $query = Ticket::query()
            ->with(['category', 'department', 'parentTicket', 'team', 'creator'])
            ->withCount('childTickets');

        if ($user->role->slug_identifier === 'supervisor') {
            $query->where('department_id', $user->department_id);
        } elseif ($user->role->slug_identifier === 'field_personnel') {
            $teamIds = $user->teams()->pluck('teams.id');
            $query->whereIn('team_id', $teamIds);
        }

        $query->search($params['search'] ?? null)
            ->filterByCategory($params['category'] ?? null)
            ->filterByStatus($params['status'] ?? null)
            ->sort($params['sort'] ?? null);

        return $query->paginate(10)->withQueryString();
    }

    /**
     * Aggregates ticket counts across all lifecycle statuses for the current user's role scope.
     * Consolidated into a single database query using SQL GROUP BY aggregation.
     */
    public function getTicketStatusCount(User $user): array
    {
        $baseQuery = Ticket::query();

        if ($user->role->slug_identifier === 'supervisor') {
            $baseQuery->where('department_id', $user->department_id);
        } elseif ($user->role->slug_identifier === 'field_personnel') {
            $teamIds = $user->teams()->pluck('teams.id');
            $baseQuery->whereIn('team_id', $teamIds);
        }

        $rawCounts = $baseQuery->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $counts = [
            'open' => (int) ($rawCounts->get(TicketStatus::OPEN->value) ?? 0),
            'assigned' => (int) ($rawCounts->get(TicketStatus::ASSIGNED->value) ?? 0),
            'in_progress' => (int) ($rawCounts->get(TicketStatus::IN_PROGRESS->value) ?? 0),
            'resolved' => (int) ($rawCounts->get(TicketStatus::RESOLVED->value) ?? 0),
            'closed' => (int) ($rawCounts->get(TicketStatus::CLOSED->value) ?? 0),
            'pending_endorsement' => (int) ($rawCounts->get(TicketStatus::PENDING_ENDORSEMENT->value) ?? 0),
            'endorsed' => (int) ($rawCounts->get(TicketStatus::ENDORSED->value) ?? 0),
            'cancelled' => (int) ($rawCounts->get(TicketStatus::CANCELLED->value) ?? 0),
        ];

        $counts['all'] = array_sum($counts);

        return $counts;
    }

    /**
     * Eagerly loads all detailed relationships required for the mobile ticket detail screen.
     */
    public function getDetailedTicketMobile(Ticket $ticket): Ticket
    {
        return $ticket->load([
            'category',
            'department',
            'parentTicket',
            'closedBy.role',
            'creator.role',
            'team.members',
            'activeAssignment.team',
            'activeAssignment.assigner.role',
            'statusLog.updater',
            'childTickets',
            'consumer',
        ])->loadCount('childTickets');
    }

    /**
     * Eager loads the comprehensive assignment and endorsement history for a ticket.
     */
    public function viewTicketHistory(Ticket $ticket): Ticket
    {
        return $ticket->load([
            'assignments.team',
            'assignments.assigner.role',
            'endorsements.suggestedDepartment',
            'endorsements.creator.role',
            'endorsements.reviewer',
        ]);
    }

    /**
     * Retrieves assignable teams within the supervisor's department with active workload metrics.
     */
    public function getAssignOptions(User $supervisor, Ticket $ticket): Collection
    {
        $teams = Team::query()
            ->where('department_id', $supervisor->department_id)
            ->withCount([
                'members',
                'tickets' => function ($query) {
                    $query->whereIn('status', [
                        TicketStatus::ASSIGNED,
                        TicketStatus::IN_PROGRESS,
                    ]);
                },
            ])
            ->get();

        $teams->each(function ($team) use ($ticket) {
            $team->is_current = ($team->id === $ticket->team_id);
        });

        return $teams;
    }
}

