<?php

namespace App\Services\Tickets;

use App\Actions\Endorsements\DecideEndorsement;
use App\Actions\Endorsements\InitiateEndorsement;
use App\Enums\EndorsementStatus;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketEndorsement;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Service encapsulating the cross-department endorsement workflow.
 * Retains dashboard queries while delegating discrete mutations to:
 * - InitiateEndorsement
 * - DecideEndorsement
 */
class TicketEndorsementService
{
    public function __construct(
        protected InitiateEndorsement $initiateEndorsementAction,
        protected DecideEndorsement $decideEndorsementAction,
        protected TicketSequenceService $sequenceService
    ) {}

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
        return $this->initiateEndorsementAction->execute($ticket, $data, $supervisor);
    }

    /**
     * Process an endorsement decision (approval creates child ticket & closes parent assignment; rejection reverts parent status).
     */
    public function verifyEndorsement(array $validatedData, TicketEndorsement $endorsement): array
    {
        return $this->decideEndorsementAction->execute($validatedData, $endorsement);
    }

    /**
     * Generate a sequential child ticket number matching parent's pattern.
     */
    public function generateChildTicketNumber(Ticket $parentTicket): string
    {
        return $this->sequenceService->generateChildTicketNumber($parentTicket);
    }
}
