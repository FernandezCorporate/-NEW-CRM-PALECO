<?php

namespace App\Services\Tickets;

use App\Enums\EndorsementStatus;
use App\Models\Department;
use App\Models\TicketEndorsement;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * Service encapsulating the cross-department endorsement queries and metrics.
 */
class TicketEndorsementService
{
    // --- QUERY METHODS ---

    /**
     * Retrieve paginated endorsements with status metric tallies for CWD dashboard.
     */
    public function getEndorsementList(Request $request): array
    {
        $endorsements = TicketEndorsement::with(['ticket', 'creator', 'suggestedDepartment', 'oldDepartment', 'newDepartment'])
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
        $endorsement->load(['ticket', 'creator', 'suggestedDepartment', 'oldDepartment', 'newDepartment']);

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
}
