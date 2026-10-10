<?php

namespace App\Services\Tickets;

use App\Enums\EndorsementStatus;
use App\Enums\TicketStatus;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketEndorsement;
use App\Models\TicketStatusLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
            ->paginate(10)
            ->withQueryString();

        $statusMetrics = [
            'pending' => TicketEndorsement::query()->where('status', EndorsementStatus::PENDING)->count(),
            'denied' => TicketEndorsement::query()->where('status', EndorsementStatus::REJECTED)->count(),
            'endorsed' => TicketEndorsement::query()->where('status', EndorsementStatus::APPROVED)->count(),
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

        return DB::transaction(function () use ($ticket, $data, $supervisor) {
            $lockedTicket = Ticket::where('id', $ticket->id)
                ->lockForUpdate()
                ->firstOrFail();

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

            return $endorsement;
        });
    }

    /**
     * Process an endorsement decision (approval creates child ticket & closes parent assignment; rejection reverts parent status).
     */
    public function verifyEndorsement(array $validatedData, TicketEndorsement $endorsement): array
    {
        return DB::transaction(function () use ($validatedData, $endorsement) {
            $lockedEndorsement = TicketEndorsement::where('id', $endorsement->id)
                ->lockForUpdate()
                ->first();

            if ($lockedEndorsement->status !== EndorsementStatus::PENDING) {
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
            } else {
                $oldStatus = $parentTicket->status;

                $parentTicket->update([
                    'status' => $lockedEndorsement->pre_endorsement_status,
                ]);

                $parentTicket->statusLog()->create([
                    'changed_by_id' => Auth::id(),
                    'old_status' => $oldStatus,
                    'new_status' => $lockedEndorsement->pre_endorsement_status,
                ]);
            }

            return ['success' => true];
        });
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
