<?php

namespace App\Services\Api\Tickets;

use App\Enums\EndorsementStatus;
use App\Enums\TicketAccomplishmentStatus;
use App\Enums\TicketStatus;
use App\Models\Department;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketAccomplishment;
use App\Models\TicketAssignment;
use App\Models\TicketEndorsement;
use App\Models\TicketStatusLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/*
 * Encapsulates the core ticket retrieval, lifecycle mutations, and operational logic for the mobile API.
 * Enforces role-based data scoping, business rule validation, and transactional database integrity.
 */
class TicketService
{
    // --- QUERY & AGGREGATION METHODS ---

    /*
     * Retrieves paginated inbox tickets filtered by user role and search parameters.
     */
    public function getInboxTickets(User $user, array $params): LengthAwarePaginator
    {
        $query = Ticket::query()
            ->with('category')
            ->withCount('childTickets');

        if ($user->role->slug_identifier === 'supervisor') {
            $query->where('department_id', $user->department_id);
        } elseif ($user->role->slug_identifier === 'field_personnel') {
            $teamIds = $user->teams()->pluck('teams.id');
            $query->whereIn('team_id', $teamIds);
        }

        $query->apiSearch($params['search'] ?? null)
            ->apiFilterByCategoryName($params['category'] ?? null)
            ->apiFilterByStatus($params['status'] ?? null)
            ->apiSort($params['sort'] ?? null);

        return $query->paginate(10)->withQueryString();
    }

    /*
     * Aggregates ticket counts across all lifecycle statuses for the current user's role scope.
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

        return [
            'all' => (clone $baseQuery)->count(),
            'open' => (clone $baseQuery)->where('status', TicketStatus::OPEN)->count(),
            'assigned' => (clone $baseQuery)->where('status', TicketStatus::ASSIGNED)->count(),
            'in_progress' => (clone $baseQuery)->where('status', TicketStatus::IN_PROGRESS)->count(),
            'resolved' => (clone $baseQuery)->where('status', TicketStatus::RESOLVED)->count(),
            'closed' => (clone $baseQuery)->where('status', TicketStatus::CLOSED)->count(),
            'pending_endorsement' => (clone $baseQuery)->where('status', TicketStatus::PENDING_ENDORSEMENT)->count(),
            'endorsed' => (clone $baseQuery)->where('status', TicketStatus::ENDORSED)->count(),
        ];
    }

    /*
     * Eagerly loads all detailed relationships required for the mobile ticket detail screen.
     */
    public function getDetailedTicketMobile(Ticket $ticket): Ticket
    {
        return $ticket->load([
            'category',
            'creator.role',
            'team.members',
            'statusLog.updater',
            'childTickets',
            'consumer',
        ])->loadCount('childTickets');
    }

    /*
     * Retrieves assignable teams within the supervisor's department with workload metrics.
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

    /*
     * Retrieves candidate departments for endorsement requests.
     */
    public function getEndorsementOptions(User $supervisor): Collection
    {
        $departments = Department::query()->get();

        $departments->each(function ($department) use ($supervisor) {
            $department->is_current = ($department->id === $supervisor->department_id);
        });

        return $departments;
    }

    /*
     * Fetches all accomplishment submissions linked to a ticket in reverse chronological order.
     */
    public function getAccomplishments(Ticket $ticket): Collection
    {
        return $ticket->accomplishments()
            ->with(['accomplishedBy', 'rejectedBy', 'approvedBy', 'photos'])
            ->latest('accomplished_at')
            ->get();
    }

    /*
     * Retrieves an individual accomplishment report with data integrity validation.
     */
    public function getAccomplishmentDetails(Ticket $ticket, TicketAccomplishment $accomplishment): TicketAccomplishment
    {
        if ($accomplishment->ticket_id !== $ticket->system_id) {
            abort(404, 'Accomplishment report not found.');
        }

        return $accomplishment->load([
            'accomplishedBy',
            'rejectedBy',
            'approvedBy',
            'photos',
        ]);
    }

    /*
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

    // --- MUTATING METHODS ---

    /*
     * Assigns or reassigns a ticket to a field team, closing previous assignments and logging status.
     *
     * @throws ValidationException
     */
    public function assignTicket(Ticket $ticket, string $teamId, User $assigner, ?string $reason = null): Ticket
    {
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

        return DB::transaction(function () use ($ticket, $teamId, $assigner, $reason) {
            $oldStatus = $ticket->status;

            $ticket->assignments()
                ->whereNull('unassigned_at')
                ->update(['unassigned_at' => now()]);

            TicketAssignment::create([
                'ticket_id' => $ticket->system_id,
                'team_id' => $teamId,
                'assigned_by' => $assigner->id,
                'reason' => $reason,
            ]);

            if ($oldStatus !== TicketStatus::ASSIGNED) {
                TicketStatusLog::create([
                    'ticket_id' => $ticket->system_id,
                    'old_status' => $oldStatus,
                    'new_status' => TicketStatus::ASSIGNED,
                    'changed_by' => $assigner->id,
                ]);
            }

            $ticket->update([
                'team_id' => $teamId,
                'status' => TicketStatus::ASSIGNED,
            ]);

            return $ticket->fresh(['category']);
        });
    }

    /*
     * Transitions an assigned ticket into IN_PROGRESS status upon field work commencement.
     *
     * @throws ValidationException
     */
    public function startTicket(Ticket $ticket, User $worker): Ticket
    {
        if ($ticket->status === TicketStatus::IN_PROGRESS) {
            throw ValidationException::withMessages([
                'status' => 'This ticket is already in progress.',
            ]);
        }

        if ($ticket->status !== TicketStatus::ASSIGNED) {
            throw ValidationException::withMessages([
                'status' => 'Only assigned tickets can be started.',
            ]);
        }

        return DB::transaction(function () use ($ticket, $worker) {
            $oldStatus = $ticket->status;

            TicketStatusLog::create([
                'ticket_id' => $ticket->system_id,
                'old_status' => $oldStatus,
                'new_status' => TicketStatus::IN_PROGRESS,
                'changed_by' => $worker->id,
            ]);

            $ticket->update([
                'status' => TicketStatus::IN_PROGRESS,
                'started_at' => now(),
            ]);

            return $ticket->fresh(['category']);
        });
    }

    /*
     * Records an accomplishment report with photographic evidence and e-signature, transitioning ticket to RESOLVED.
     *
     * @throws ValidationException
     */
    public function accomplishTicket(Ticket $ticket, User $worker, array $accomplishmentDetails): TicketAccomplishment
    {
        if ($ticket->status === TicketStatus::RESOLVED) {
            throw ValidationException::withMessages([
                'status' => 'An accomplishment report has already been submitted for this ticket.',
            ]);
        }

        if ($ticket->status !== TicketStatus::IN_PROGRESS) {
            throw ValidationException::withMessages([
                'status' => 'Only tickets that are in progress can be marked as accomplished.',
            ]);
        }

        $signaturePath = null;
        $photoPaths = [];

        try {
            return DB::transaction(function () use ($ticket, $worker, $accomplishmentDetails, &$signaturePath, &$photoPaths) {
                $signaturePath = $accomplishmentDetails['signature']->store("accomplishments/{$ticket->system_id}/signature", 'public');

                foreach ($accomplishmentDetails['photos'] as $photo) {
                    $photoPaths[] = $photo->store("accomplishments/{$ticket->system_id}/photos", 'public');
                }

                $report = TicketAccomplishment::create([
                    'ticket_id' => $ticket->system_id,
                    'accomplished_by_id' => $worker->id,
                    'remarks' => $accomplishmentDetails['remarks'],
                    'consumer_name' => $accomplishmentDetails['consumer_name'] ?? null,
                    'signature_path' => $signaturePath,
                    'status' => TicketAccomplishmentStatus::PENDING,
                    'accomplished_at' => now(),
                ]);

                $photoRecords = array_map(fn ($path) => ['file_path' => $path], $photoPaths);
                $report->photos()->createMany($photoRecords);

                TicketStatusLog::create([
                    'ticket_id' => $ticket->system_id,
                    'old_status' => $ticket->status,
                    'new_status' => TicketStatus::RESOLVED,
                    'changed_by' => $worker->id,
                ]);

                $ticket->update([
                    'status' => TicketStatus::RESOLVED,
                    'resolved_at' => now(),
                ]);

                return $report;
            });
        } catch (\Exception $e) {
            if ($signaturePath) {
                Storage::disk('public')->delete($signaturePath);
            }
            if (! empty($photoPaths)) {
                Storage::disk('public')->delete($photoPaths);
            }

            throw $e;
        }
    }

    /*
     * Evaluates a submitted accomplishment report (approves closing the ticket or rejects back to in-progress).
     *
     * @throws ValidationException
     */
    public function verifyAccomplishment(Ticket $ticket, TicketAccomplishment $accomplishment, array $data, User $supervisor): TicketAccomplishment
    {
        if ($accomplishment->ticket_id !== $ticket->system_id) {
            abort(404, 'This accomplishment report does not belong to the requested ticket.');
        }

        if ($accomplishment->status !== TicketAccomplishmentStatus::PENDING) {
            throw ValidationException::withMessages([
                'status' => 'This accomplishment report has already been evaluated.',
            ]);
        }

        return DB::transaction(function () use ($ticket, $accomplishment, $data, $supervisor) {
            $oldTicketStatus = $ticket->status;

            if ($data['status'] === TicketAccomplishmentStatus::APPROVED->value) {
                $accomplishment->update([
                    'status' => TicketAccomplishmentStatus::APPROVED,
                    'approved_by_id' => $supervisor->id,
                ]);

                $ticket->update([
                    'status' => TicketStatus::CLOSED,
                    'closed_at' => now(),
                ]);

                TicketStatusLog::create([
                    'ticket_id' => $ticket->system_id,
                    'old_status' => $oldTicketStatus,
                    'new_status' => TicketStatus::CLOSED,
                    'changed_by' => $supervisor->id,
                ]);
            }

            if ($data['status'] === TicketAccomplishmentStatus::REJECTED->value) {
                $accomplishment->update([
                    'status' => TicketAccomplishmentStatus::REJECTED,
                    'rejection_reason' => $data['rejection_reason'],
                    'rejected_by_id' => $supervisor->id,
                ]);

                $ticket->update([
                    'status' => TicketStatus::IN_PROGRESS,
                    'resolved_at' => null,
                ]);

                TicketStatusLog::create([
                    'ticket_id' => $ticket->system_id,
                    'old_status' => $oldTicketStatus,
                    'new_status' => TicketStatus::IN_PROGRESS,
                    'changed_by' => $supervisor->id,
                ]);
            }

            return $accomplishment->fresh(['accomplishedBy', 'rejectedBy', 'approvedBy', 'photos']);
        });
    }

    /*
     * Initiates an endorsement request to another department, freezing ticket progress pending review.
     *
     * @throws ValidationException
     */
    public function requestEndorsement(Ticket $ticket, array $data, User $supervisor): TicketEndorsement
    {
        $allowedStatuses = [
            TicketStatus::OPEN,
            TicketStatus::ASSIGNED,
            TicketStatus::IN_PROGRESS,
        ];

        if (! in_array($ticket->status, $allowedStatuses, true)) {
            throw ValidationException::withMessages([
                'status' => 'Tickets that are resolved, closed, or locked in an endorsement workflow cannot be endorsed.',
            ]);
        }

        if ($ticket->endorsements()->where('status', EndorsementStatus::PENDING->value)->exists()) {
            throw ValidationException::withMessages([
                'status' => 'This ticket already has a pending endorsement request.',
            ]);
        }

        return DB::transaction(function () use ($ticket, $data, $supervisor) {
            $endorsement = $ticket->endorsements()->create([
                'created_by' => $supervisor->id,
                'suggested_department_id' => $data['suggested_department_id'] ?? null,
                'reason' => $data['reason'],
                'pre_endorsement_status' => $ticket->status->value,
                'status' => EndorsementStatus::PENDING,
            ]);

            $ticket->update([
                'status' => TicketStatus::PENDING_ENDORSEMENT,
            ]);

            return $endorsement;
        });
    }
}
