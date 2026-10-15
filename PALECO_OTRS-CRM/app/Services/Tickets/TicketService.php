<?php

namespace App\Services\Tickets;

use App\Enums\ComplaintSources;
use App\Enums\TicketStatus;
use App\Events\TicketAssigned;
use App\Events\TicketCancelled;
use App\Events\TicketCreated;
use App\Events\TicketForceClosed;
use App\Events\TicketWorkStarted;
use App\Models\Department;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\TicketCategory;
use App\Models\TicketStatusLog;
use App\Models\User;
use App\Services\External\ConsumerService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Encapsulates the core service ticket lifecycle:
 * generation, assignments, state mutations, and query aggregation across Web and Mobile portals.
 */
class TicketService
{
    public function __construct(
        protected ConsumerService $consumerService
    ) {}

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
                'ticket' => function ($query) {
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

    // --- MUTATING METHODS ---

    /**
     * Persist a new consumer ticket along with initial status log and safe deferred event dispatch.
     * Retries automatically if a concurrent race condition collides on the initial sequence number.
     */
    public function createCwdTicket(array $validatedData): Ticket
    {
        if (! empty($validatedData['link_consumer']) && ! empty($validatedData['account_code'])) {
            $validatedData['consumer_id'] = $this->consumerService->resolveConsumerId($validatedData['account_code']);
        } else {
            $validatedData['consumer_id'] = null;
        }

        $maxAttempts = 3;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $attempt++;

            try {
                return DB::transaction(function () use ($validatedData) {
                    $ticketNumber = $this->generateSequentialNumber();

                    $ticket = Ticket::create(array_merge($validatedData, [
                        'ticket_number' => $ticketNumber,
                        'status' => TicketStatus::OPEN,
                        'created_by_id' => Auth::id(),
                        'reported_at' => now(),
                    ]));

                    $ticket->statusLog()->create([
                        'changed_by_id' => Auth::id(),
                        'old_status' => null,
                        'new_status' => TicketStatus::OPEN,
                    ]);

                    // Defer broadcast until after database transaction commits
                    DB::afterCommit(function () use ($ticket) {
                        TicketCreated::dispatch($ticket->load(['category', 'department']));
                    });

                    return $ticket;
                });
            } catch (QueryException $e) {
                // If collision occurred on unique ticket_number constraint, retry with next sequence
                $isDuplicateKey = ($e->errorInfo[1] ?? null) === 1062
                    || ($e->errorInfo[1] ?? null) === 19
                    || str_contains($e->getMessage(), 'Duplicate entry')
                    || str_contains($e->getMessage(), 'UNIQUE constraint failed');

                if ($attempt < $maxAttempts && $isDuplicateKey) {
                    usleep(50000 * $attempt); // 50ms backoff

                    continue;
                }

                throw $e;
            }
        }

        throw new \RuntimeException('Failed to generate a unique ticket sequence number after multiple attempts.');
    }

    /**
     * Manually spawn a child ticket linked directly to the parent ticket.
     * Retries automatically if a concurrent race condition collides on the child sequence number.
     */
    public function createManualChildTicket(Ticket $parentTicket, array $validatedData): Ticket
    {
        $maxAttempts = 3;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $attempt++;

            try {
                return DB::transaction(function () use ($parentTicket, $validatedData) {
                    $lockedParent = Ticket::where('id', $parentTicket->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if (! is_null($lockedParent->parent_ticket_id)) {
                        throw ValidationException::withMessages([
                            'parent_ticket_id' => 'Cannot create a child ticket from another child ticket. Only root origin tickets can spawn child tickets.',
                        ]);
                    }

                    if ($lockedParent->status === TicketStatus::PENDING_ENDORSEMENT) {
                        throw ValidationException::withMessages([
                            'parent_ticket_id' => 'Cannot spawn child tickets from a ticket that is pending endorsement.',
                        ]);
                    }

                    if ($lockedParent->status === TicketStatus::CANCELLED) {
                        throw ValidationException::withMessages([
                            'parent_ticket_id' => 'Cannot spawn child tickets from a ticket that is cancelled.',
                        ]);
                    }

                    if ($lockedParent->status === TicketStatus::CLOSED) {
                        throw ValidationException::withMessages([
                            'parent_ticket_id' => 'Cannot spawn child tickets from a ticket that is closed.',
                        ]);
                    }

                    $childTicket = Ticket::create([
                        'ticket_number' => $this->generateChildTicketNumber($lockedParent),
                        'parent_ticket_id' => $lockedParent->id,
                        'department_id' => $validatedData['department_id'],

                        'consumer_id' => $lockedParent->consumer_id,
                        'consumer_contact' => $validatedData['consumer_contact'] ?? $lockedParent->consumer_contact,
                        'complaint_source' => $lockedParent->complaint_source,
                        'complaint_description' => $validatedData['complaint_description'],

                        'category_id' => $validatedData['category_id'] ?? null,
                        'other_category' => $validatedData['other_category'] ?? false,
                        'other_category_name' => $validatedData['other_category_name'] ?? null,

                        'purok' => $validatedData['purok'] ?? $lockedParent->purok,
                        'street' => $validatedData['street'] ?? $lockedParent->street,
                        'barangay' => $validatedData['barangay'] ?? $lockedParent->barangay,
                        'landmark' => $validatedData['landmark'] ?? $lockedParent->landmark,

                        'status' => TicketStatus::OPEN,
                        'created_by_id' => Auth::id(),
                        'reported_at' => now(),
                    ]);

                    $childTicket->statusLog()->create([
                        'changed_by_id' => Auth::id(),
                        'old_status' => null,
                        'new_status' => TicketStatus::OPEN,
                    ]);

                    $lockedParent->remarks()->create([
                        'user_id' => Auth::id(),
                        'body' => "Child Ticket {$childTicket->ticket_number} was created.",
                        'is_internal' => true,
                    ]);

                    // Defer broadcast until after database transaction commits
                    DB::afterCommit(function () use ($childTicket) {
                        TicketCreated::dispatch($childTicket->load(['category', 'department']));
                    });

                    return $childTicket;
                });
            } catch (QueryException $e) {
                // If collision occurred on unique ticket_number constraint, retry with next sequence
                $isDuplicateKey = ($e->errorInfo[1] ?? null) === 1062
                    || ($e->errorInfo[1] ?? null) === 19
                    || str_contains($e->getMessage(), 'Duplicate entry')
                    || str_contains($e->getMessage(), 'UNIQUE constraint failed');

                if ($attempt < $maxAttempts && $isDuplicateKey) {
                    usleep(50000 * $attempt); // 50ms backoff

                    continue;
                }

                throw $e;
            }
        }

        throw new \RuntimeException('Failed to generate a unique child ticket sequence number after multiple attempts.');
    }

    /**
     * Updates clerical intake details (contact, location, description, category, consumer link) for an Open ticket.
     *
     * @return array{success: bool, changed: bool, ticket: Ticket}
     */
    public function updateIntakeDetails(Ticket $ticket, array $validatedData): array
    {
        if (! empty($validatedData['link_consumer']) && ! empty($validatedData['account_code'])) {
            $validatedData['consumer_id'] = $this->consumerService->resolveConsumerId($validatedData['account_code']);
        } else {
            $validatedData['consumer_id'] = null;
        }

        unset($validatedData['link_consumer'], $validatedData['account_code']);

        if (! empty($validatedData['other_category'])) {
            $validatedData['category_id'] = null;
        } else {
            $validatedData['other_category_name'] = null;
        }

        return DB::transaction(function () use ($ticket, $validatedData) {
            $lockedTicket = Ticket::where('id', $ticket->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedTicket->status !== TicketStatus::OPEN) {
                throw ValidationException::withMessages([
                    'status' => 'This ticket is no longer in Open status (it may have been assigned to a crew or processed concurrently) and can no longer be edited.',
                ]);
            }

            $lockedTicket->fill($validatedData);

            if ($lockedTicket->isClean()) {
                return [
                    'success' => true,
                    'changed' => false,
                    'ticket' => $lockedTicket,
                ];
            }

            $lockedTicket->save();

            if (Auth::check()) {
                $lockedTicket->remarks()->create([
                    'user_id' => Auth::id(),
                    'body' => 'Clerical intake details updated.',
                    'is_internal' => true,
                ]);
            }

            return [
                'success' => true,
                'changed' => true,
                'ticket' => $lockedTicket,
            ];
        });
    }

    /**
     * Reassigns an operational ticket to a target team, closing previous assignment timestamps.
     *
     * @throws ValidationException
     */
    public function assignTicket(Ticket $ticket, string $teamId, User $assigner, ?string $reason = null): Ticket
    {
        if ($ticket->status === TicketStatus::PENDING_ENDORSEMENT) {
            throw ValidationException::withMessages([
                'status' => 'Ticket is locked pending inter-departmental endorsement review.',
            ]);
        }

        $allowedStatuses = [
            TicketStatus::OPEN,
            TicketStatus::ASSIGNED,
            TicketStatus::IN_PROGRESS,
        ];

        if (! in_array($ticket->status, $allowedStatuses, true)) {
            throw ValidationException::withMessages([
                'status' => 'Tickets that are resolved, closed, or has a pending endorsement cannot be reassigned.',
            ]);
        }

        if ($ticket->team_id === $teamId) {
            throw ValidationException::withMessages([
                'team_id' => 'This ticket is already assigned to the selected team. No changes were made.',
            ]);
        }

        $team = Team::where('id', $teamId)
            ->whereNull('deleted_at')
            ->first();

        if (! $team || ($ticket->department_id && $team->department_id !== $ticket->department_id)) {
            throw ValidationException::withMessages([
                'team_id' => 'The selected team is invalid, archived, or does not belong to the ticket\'s department.',
            ]);
        }

        return DB::transaction(function () use ($ticket, $team, $teamId, $assigner, $reason, $allowedStatuses) {
            $lockedTicket = Ticket::where('id', $ticket->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($lockedTicket->status, $allowedStatuses, true)) {
                throw ValidationException::withMessages([
                    'status' => 'Tickets that are resolved, closed, or has a pending endorsement cannot be reassigned.',
                ]);
            }

            if ($lockedTicket->status === TicketStatus::PENDING_ENDORSEMENT) {
                throw ValidationException::withMessages([
                    'status' => 'Ticket is locked pending inter-departmental endorsement review.',
                ]);
            }

            if ($lockedTicket->department_id && $team->department_id !== $lockedTicket->department_id) {
                throw ValidationException::withMessages([
                    'team_id' => 'The selected team does not belong to the ticket\'s department (the ticket may have been re-routed concurrently).',
                ]);
            }

            $oldStatus = $lockedTicket->status;

            // Close active assignment history record
            $lockedTicket->assignments()
                ->whereNull('unassigned_at')
                ->update(['unassigned_at' => now()]);

            TicketAssignment::create([
                'ticket_id' => $lockedTicket->id,
                'team_id' => $teamId,
                'assigned_by_id' => $assigner->id,
                'reason' => $reason,
            ]);

            if ($oldStatus !== TicketStatus::ASSIGNED) {
                TicketStatusLog::create([
                    'ticket_id' => $lockedTicket->id,
                    'old_status' => $oldStatus,
                    'new_status' => TicketStatus::ASSIGNED,
                    'changed_by_id' => $assigner->id,
                ]);
            }

            $lockedTicket->update([
                'team_id' => $teamId,
                'status' => TicketStatus::ASSIGNED,
            ]);

            $freshTicket = $lockedTicket->fresh([
                'category',
                'team',
                'department',
                'activeAssignment.team',
                'activeAssignment.assigner.role',
            ]);

            DB::afterCommit(function () use ($freshTicket) {
                TicketAssigned::dispatch($freshTicket);
            });

            return $freshTicket;
        });
    }

    /**
     * Transitions an assigned ticket into IN_PROGRESS status upon field work commencement.
     *
     * @throws ValidationException
     */
    public function startTicket(Ticket $ticket, User $worker, ?Carbon $clientTimestamp = null): Ticket
    {
        return DB::transaction(function () use ($ticket, $worker, $clientTimestamp) {
            $lockedTicket = Ticket::where('id', $ticket->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Idempotency safety: If already in progress, return ticket safely without throwing error
            if ($lockedTicket->status === TicketStatus::IN_PROGRESS) {
                return $lockedTicket->fresh(['category']);
            }

            if ($lockedTicket->status === TicketStatus::PENDING_ENDORSEMENT) {
                throw ValidationException::withMessages([
                    'status' => 'Ticket is locked pending inter-departmental endorsement review.',
                ]);
            }

            if ($lockedTicket->status !== TicketStatus::ASSIGNED) {
                throw ValidationException::withMessages([
                    'status' => 'Only assigned tickets can be started.',
                ]);
            }

            $isOffline = $clientTimestamp !== null && $clientTimestamp->diffInMinutes(now()) > 5;
            $actualStartTime = $clientTimestamp ?? now();

            $oldStatus = $lockedTicket->status;

            TicketStatusLog::create([
                'ticket_id' => $lockedTicket->id,
                'old_status' => $oldStatus,
                'new_status' => TicketStatus::IN_PROGRESS,
                'changed_by_id' => $worker->id,
                'created_at' => $actualStartTime,
            ]);

            $lockedTicket->update([
                'status' => TicketStatus::IN_PROGRESS,
                'started_at' => $actualStartTime,
                'client_started_at' => $clientTimestamp,
                'is_offline_synced' => $isOffline,
                'synced_at' => $isOffline ? now() : null,
            ]);

            $freshTicket = $lockedTicket->fresh(['category', 'team', 'department']);

            DB::afterCommit(function () use ($freshTicket) {
                TicketWorkStarted::dispatch($freshTicket);
            });

            return $freshTicket;
        });
    }

    /**
     * Cancels an open service ticket upon dispatcher review.
     * Pessimistically locks the ticket, verifies OPEN status and CWD role,
     * logs status transition to CANCELLED, and appends a freely typed audit remark.
     *
     * @throws ValidationException
     */
    public function cancelTicket(Ticket $ticket, array $data, User $actor): Ticket
    {
        return DB::transaction(function () use ($ticket, $data, $actor) {
            $lockedTicket = Ticket::where('id', $ticket->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($actor->role->slug_identifier !== 'cwd_officer') {
                throw ValidationException::withMessages([
                    'actor' => 'Only CWD officers are authorized to cancel tickets.',
                ]);
            }

            if ($lockedTicket->status === TicketStatus::CANCELLED) {
                throw ValidationException::withMessages([
                    'status' => 'This ticket is already cancelled.',
                ]);
            }

            if ($lockedTicket->status !== TicketStatus::OPEN) {
                throw ValidationException::withMessages([
                    'status' => 'Ticket can only be cancelled while in Open status. Once assigned to a team or dispatched, cancellation is locked.',
                ]);
            }

            $oldStatus = $lockedTicket->status;

            // Create immutable status transition log
            TicketStatusLog::create([
                'ticket_id' => $lockedTicket->id,
                'old_status' => $oldStatus,
                'new_status' => TicketStatus::CANCELLED,
                'changed_by_id' => $actor->id,
            ]);

            // Update ticket status, freely typed cancellation reason, and cancelled timestamp
            $lockedTicket->update([
                'status' => TicketStatus::CANCELLED,
                'cancellation_reason' => trim($data['cancellation_reason']),
                'cancelled_at' => now(),
            ]);

            DB::afterCommit(fn () => event(new TicketCancelled($lockedTicket)));

            return $lockedTicket->fresh([
                'category',
                'department',
                'team',
                'statusLog.updater',
            ]);
        });
    }

    /**
     * Administratively force-closes an active service ticket.
     * Pessimistically locks the ticket, enforces status eligibility and role authorization,
     * requires all subordinate child tickets to be exactly closed or cancelled,
     * terminates any active field team assignment, records status transition, and sets closure metadata.
     *
     * @throws ValidationException
     */
    public function forceCloseTicket(Ticket $ticket, array $data, User $actor): Ticket
    {
        return DB::transaction(function () use ($ticket, $data, $actor) {
            $lockedTicket = Ticket::where('id', $ticket->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($actor->role->slug_identifier !== 'cwd_officer') {
                throw ValidationException::withMessages([
                    'actor' => 'Only CWD Officers are authorized to force-close tickets.',
                ]);
            }

            if ($lockedTicket->status === TicketStatus::CLOSED) {
                throw ValidationException::withMessages([
                    'status' => 'This ticket is already closed.',
                ]);
            }

            if ($lockedTicket->status === TicketStatus::CANCELLED) {
                throw ValidationException::withMessages([
                    'status' => 'Cancelled tickets cannot be force-closed.',
                ]);
            }

            if ($lockedTicket->status === TicketStatus::OPEN) {
                throw ValidationException::withMessages([
                    'status' => 'Open tickets must be cancelled via the cancellation workflow, not force-closed.',
                ]);
            }

            if ($lockedTicket->status === TicketStatus::PENDING_ENDORSEMENT) {
                throw ValidationException::withMessages([
                    'status' => 'Tickets pending endorsement review cannot be force-closed. Complete the transfer decision first.',
                ]);
            }

            $allowedStatuses = [
                TicketStatus::ASSIGNED,
                TicketStatus::IN_PROGRESS,
                TicketStatus::RESOLVED,
                TicketStatus::ENDORSED,
            ];

            if (! in_array($lockedTicket->status, $allowedStatuses, true)) {
                throw ValidationException::withMessages([
                    'status' => 'Ticket cannot be force-closed from its current status (' . $lockedTicket->status->label() . ').',
                ]);
            }

            // Subordinate child tickets check: require ALL child tickets to be exactly CLOSED or CANCELLED
            $childTickets = Ticket::where('parent_ticket_id', $lockedTicket->id)
                ->lockForUpdate()
                ->get();

            if ($childTickets->isNotEmpty()) {
                $hasUnresolved = $childTickets->contains(function (Ticket $child) {
                    return ! in_array($child->status, [TicketStatus::CLOSED, TicketStatus::CANCELLED], true);
                });

                if ($hasUnresolved) {
                    $nonTerminal = $childTickets->reject(function (Ticket $child) {
                        return in_array($child->status, [TicketStatus::CLOSED, TicketStatus::CANCELLED], true);
                    })->map(fn (Ticket $child) => "{$child->ticket_number} ({$child->status->label()})")->implode(', ');

                    throw ValidationException::withMessages([
                        'child_tickets' => "Cannot force-close ticket while subordinate child tickets are active. All child tickets must be exactly closed or cancelled. Active children: {$nonTerminal}",
                    ]);
                }
            }

            $oldStatus = $lockedTicket->status;
            $assignedTeamId = $lockedTicket->team_id;

            // Close active field team assignment immediately
            $lockedTicket->assignments()
                ->whereNull('unassigned_at')
                ->update(['unassigned_at' => now()]);

            // Update ticket to CLOSED with force-closure audit attributes (preserving assigned team for audit & crew visibility)
            $lockedTicket->update([
                'status' => TicketStatus::CLOSED,
                'is_force_closed' => true,
                'closure_reason' => trim($data['closure_reason']),
                'closed_by_id' => $actor->id,
                'closed_at' => now(),
            ]);

            // Create immutable status transition log
            TicketStatusLog::create([
                'ticket_id' => $lockedTicket->id,
                'old_status' => $oldStatus->value,
                'new_status' => TicketStatus::CLOSED->value,
                'changed_by_id' => $actor->id,
            ]);

            DB::afterCommit(fn () => event(new TicketForceClosed($lockedTicket, $assignedTeamId)));

            return $lockedTicket->fresh([
                'category',
                'department',
                'team',
                'closedBy.role',
                'statusLog.updater',
            ]);
        });
    }

    // --- PRIVATE HELPER METHODS ---

    /**
     * Generate a unique sequential ticket tracking number for the current date.
     * Restricts search strictly to root tickets (parent_ticket_id is null) to prevent
     * child ticket numbers (e.g. TKT-261007-005-1) from contaminating sequence arithmetic.
     */
    public function generateSequentialNumber(): string
    {
        $dateCode = now()->format('ymd');

        $latestMatch = Ticket::where('ticket_number', 'like', "TKT-{$dateCode}-%")
            ->whereNull('parent_ticket_id')
            ->lockForUpdate()
            ->orderBy('ticket_number', 'desc')
            ->first();

        $nextSequence = 1;

        if ($latestMatch) {
            $parts = explode('-', $latestMatch->ticket_number);
            $lastAssignedDigits = (int) end($parts);
            $nextSequence = $lastAssignedDigits + 1;
        }

        return sprintf('TKT-%s-%03d', $dateCode, $nextSequence);
    }

    /**
     * Generate a sequential child ticket number matching parent's pattern.
     */
    public function generateChildTicketNumber(Ticket $parentTicket): string
    {
        $baseNumber = $parentTicket->ticket_number;

        $existingChildren = Ticket::where('ticket_number', 'like', "{$baseNumber}-%")
            ->lockForUpdate()
            ->pluck('ticket_number');

        $maxSequence = 0;
        foreach ($existingChildren as $childNumber) {
            $parts = explode('-', $childNumber);
            $suffix = (int) end($parts);
            if ($suffix > $maxSequence) {
                $maxSequence = $suffix;
            }
        }

        return sprintf('%s-%d', $baseNumber, $maxSequence + 1);
    }
}
