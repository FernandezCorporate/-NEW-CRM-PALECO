<?php

namespace App\Services\Tickets;

use App\Enums\EndorsementStatus;
use App\Enums\TicketStatus;
use App\Events\TicketCreated;
use App\Events\TicketEndorsementDecided;
use App\Events\TicketEndorsementInitiated;
use App\Models\Department;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketEndorsement;
use App\Models\TicketStatusLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Encapsulates the cross-department endorsement workflow:
 * endorsement requests, CWD supervisory review, and child ticket spawning.
 */
class TicketEndorsementService
{
    // --- QUERY METHODS ---

    /**
     * Retrieve paginated endorsements with status metric tallies for CWD dashboard.
     */
    public function getEndorsementList(Request $request): array
    {
        $endorsements = TicketEndorsement::with(['ticket', 'creator', 'suggestedDepartment'])
            ->search($request->search)
            ->filterByStatus($request->status)
            ->latest('created_at')
            ->paginate(10)
            ->withQueryString();

        $rawCounts = TicketEndorsement::query()
            ->search($request->search)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $statusCounts = [];
        foreach ($rawCounts as $st => $cnt) {
            $key = $st instanceof EndorsementStatus ? $st->value : (string) $st;
            $statusCounts[$key] = (int) $cnt;
        }

        $totalCount = array_sum($statusCounts);

        $statusMetrics = [
            'total' => $totalCount,
            EndorsementStatus::PENDING->value => $statusCounts[EndorsementStatus::PENDING->value] ?? 0,
            EndorsementStatus::APPROVED->value => $statusCounts[EndorsementStatus::APPROVED->value] ?? 0,
            EndorsementStatus::REJECTED->value => $statusCounts[EndorsementStatus::REJECTED->value] ?? 0,
            'pending' => $statusCounts[EndorsementStatus::PENDING->value] ?? 0,
            'denied' => $statusCounts[EndorsementStatus::REJECTED->value] ?? 0,
            'endorsed' => $statusCounts[EndorsementStatus::APPROVED->value] ?? 0,
        ];

        $statuses = EndorsementStatus::cases();

        return [
            'endorsements' => $endorsements,
            'statusMetrics' => $statusMetrics,
            'statuses' => $statuses,
        ];
    }

    /**
     * Load endorsement record details with alternate department options for CWD decision form.
     */
    public function getEndorsementDetails(TicketEndorsement $endorsement): array
    {
        $endorsement->load(['ticket', 'creator', 'suggestedDepartment']);

        $approveValue = EndorsementStatus::APPROVED;
        $rejectValue = EndorsementStatus::REJECTED;

        $departments = Department::query()
            ->where('id', '!=', $endorsement->ticket->department_id)
            ->get();

        return [
            'endorsement' => $endorsement,
            'approveValue' => $approveValue,
            'rejectValue' => $rejectValue,
            'departments' => $departments,
        ];
    }

    /**
     * Retrieves potential candidate departments for endorsement (excluding the supervisor's own department).
     */
    public function getEndorsementOptions(User $user): Collection
    {
        return Department::query()
            ->where('id', '!=', $user->department_id)
            ->orderBy('dept_name', 'asc')
            ->select(['id', 'dept_name'])
            ->get();
    }

    // --- MUTATING METHODS ---

    /**
     * Initiates an endorsement request to another department, freezing ticket progress pending CWD review.
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

        return DB::transaction(function () use ($ticket, $data, $supervisor, $allowedStatuses) {
            $lockedTicket = Ticket::where('id', $ticket->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($lockedTicket->status, $allowedStatuses, true)) {
                throw ValidationException::withMessages([
                    'status' => 'Tickets that are resolved, closed, or locked in an endorsement workflow cannot be endorsed.',
                ]);
            }

            if ($lockedTicket->endorsements()->where('status', EndorsementStatus::PENDING->value)->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'This ticket already has a pending endorsement request.',
                ]);
            }

            $oldStatus = $lockedTicket->status;

            $endorsement = $lockedTicket->endorsements()->create([
                'created_by_id' => $supervisor->id,
                'suggested_department_id' => $data['suggested_department_id'] ?? null,
                'reason' => $data['reason'],
                'pre_endorsement_status' => $oldStatus->value,
                'status' => EndorsementStatus::PENDING,
            ]);

            TicketStatusLog::create([
                'ticket_id' => $lockedTicket->id,
                'old_status' => $oldStatus,
                'new_status' => TicketStatus::PENDING_ENDORSEMENT,
                'changed_by_id' => $supervisor->id,
            ]);

            $lockedTicket->update([
                'status' => TicketStatus::PENDING_ENDORSEMENT,
            ]);

            DB::afterCommit(function () use ($endorsement) {
                $endorsement->load(['ticket', 'suggestedDepartment', 'creator']);
                rescue(fn () => event(new TicketEndorsementInitiated($endorsement)), report: true);
            });

            return $endorsement;
        });
    }

    /**
     * Process an endorsement decision (approval creates child ticket & closes parent assignment; rejection reverts parent status).
     */
    public function verifyEndorsement(array $validatedData, TicketEndorsement $endorsement): array
    {
        $maxAttempts = 3;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $attempt++;

            try {
                return DB::transaction(function () use ($validatedData, $endorsement) {
                    $lockedEndorsement = TicketEndorsement::where('id', $endorsement->id)
                        ->lockForUpdate()
                        ->first();

                    if (! $lockedEndorsement || $lockedEndorsement->status !== EndorsementStatus::PENDING) {
                        return ['success' => false, 'message' => 'This endorsement has already been processed by another officer.'];
                    }

                    $isApproved = $validatedData['status'] === EndorsementStatus::APPROVED->value;

                    $lockedEndorsement->update([
                        'status' => $validatedData['status'],
                        'reviewed_by_id' => Auth::id(),
                        'reviewed_at' => now(),
                        'rejection_reason' => $isApproved ? null : $validatedData['rejection_reason'],
                    ]);

                    $parentTicket = $lockedEndorsement->ticket()->lockForUpdate()->first();

                    if ($isApproved) {
                        $oldParentStatus = $parentTicket->status;

                        // Close active assignment on the parent ticket since responsibility is transferred
                        $parentTicket->assignments()
                            ->whereNull('unassigned_at')
                            ->update(['unassigned_at' => now()]);

                        $parentTicket->update([
                            'status' => TicketStatus::ENDORSED,
                            'team_id' => null,
                        ]);

                        $parentTicket->statusLog()->create([
                            'changed_by_id' => Auth::id(),
                            'old_status' => $oldParentStatus,
                            'new_status' => TicketStatus::ENDORSED,
                        ]);

                        // Spawn child ticket inheriting core metadata
                        $childTicket = Ticket::create([
                            'ticket_number' => $this->generateChildTicketNumber($parentTicket),
                            'parent_ticket_id' => $parentTicket->getKey(),
                            'department_id' => $validatedData['department_id'],

                            'consumer_id' => $parentTicket->consumer_id,
                            'consumer_contact' => $parentTicket->consumer_contact,
                            'complaint_source' => $parentTicket->complaint_source,
                            'complaint_description' => $parentTicket->complaint_description,

                            'category_id' => $parentTicket->category_id,
                            'other_category' => $parentTicket->other_category,
                            'other_category_name' => $parentTicket->other_category_name,

                            'purok' => $parentTicket->purok,
                            'street' => $parentTicket->street,
                            'barangay' => $parentTicket->barangay,
                            'landmark' => $parentTicket->landmark,

                            'subject' => $this->generateChildTicketSubject($parentTicket),
                            'status' => TicketStatus::OPEN,
                            'created_by_id' => Auth::id(),
                            'reported_at' => now(),
                        ]);

                        $childTicket->statusLog()->create([
                            'changed_by_id' => Auth::id(),
                            'old_status' => null,
                            'new_status' => TicketStatus::OPEN,
                        ]);

                        DB::afterCommit(function () use ($lockedEndorsement, $childTicket) {
                            $lockedEndorsement->load(['ticket', 'reviewer', 'suggestedDepartment']);
                            rescue(fn () => event(new TicketCreated($childTicket)), report: true);
                            rescue(fn () => event(new TicketEndorsementDecided($lockedEndorsement, $childTicket->id, $childTicket->ticket_number)), report: true);
                        });
                    } else {
                        $oldStatus = $parentTicket->status;
                        $rawPreStatus = $lockedEndorsement->pre_endorsement_status;
                        $targetStatus = $rawPreStatus instanceof TicketStatus
                            ? $rawPreStatus
                            : TicketStatus::tryFrom((string) $rawPreStatus);
                        $targetTeamId = $parentTicket->team_id;

                        // Check if previously assigned team is still active and not deleted
                        $isTeamValid = false;
                        if ($targetTeamId) {
                            $isTeamValid = Team::where('id', $targetTeamId)->exists();
                        }

                        if (! $isTeamValid && in_array($targetStatus, [TicketStatus::ASSIGNED, TicketStatus::IN_PROGRESS], true)) {
                            // Previous team was deleted/archived while endorsement was pending.
                            // Gracefully fallback to OPEN so it can be reassigned to an active crew.
                            $targetStatus = TicketStatus::OPEN;
                            $targetTeamId = null;

                            // Close any lingering active assignment record
                            $parentTicket->assignments()
                                ->whereNull('unassigned_at')
                                ->update(['unassigned_at' => now()]);

                            $parentTicket->remarks()->create([
                                'user_id' => Auth::id(),
                                'body' => 'Endorsement was rejected, but the previously assigned team is no longer active. Ticket was reverted to OPEN for re-assignment.',
                                'is_internal' => true,
                            ]);
                        }

                        $parentTicket->update([
                            'status' => $targetStatus,
                            'team_id' => $targetTeamId,
                        ]);

                        $parentTicket->statusLog()->create([
                            'changed_by_id' => Auth::id(),
                            'old_status' => $oldStatus,
                            'new_status' => $targetStatus,
                        ]);

                        DB::afterCommit(function () use ($lockedEndorsement) {
                            $lockedEndorsement->load(['ticket', 'reviewer', 'suggestedDepartment']);
                            rescue(fn () => event(new TicketEndorsementDecided($lockedEndorsement)), report: true);
                        });
                    }

                    return ['success' => true];
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

        throw new RuntimeException('Failed to process endorsement due to concurrent ticket sequence collision.');
    }

    // --- PRIVATE HELPER METHODS ---

    /**
     * Generate the child ticket subject line from parent ticket.
     */
    private function generateChildTicketSubject(Ticket $parentTicket): string
    {
        return 'Endorsed: '.$parentTicket->subject;
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
