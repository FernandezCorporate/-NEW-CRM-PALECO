<?php

namespace App\Services\Web\Cwd;

use App\Enums\ComplaintSources;
use App\Enums\EndorsementStatus;
use App\Enums\TicketStatus;
use App\Events\TicketCreated;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketAccomplishment;
use App\Models\TicketCategory;
use App\Models\TicketEndorsement;
use App\Services\External\ConsumerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Encapsulates the core backend processing for Service Tickets, endorsements,
 * accomplishments, and hierarchy relations.
 */
class TicketService
{
    public function __construct(protected ConsumerService $consumerService) {}

    // --- QUERY METHODS ---

    /**
     * Retrieve paginated tickets filtered by search query, category, and status.
     */
    public function getTicketList(Request $request): array
    {
        $tickets = Ticket::with(['category', 'department', 'creator', 'parentTicket'])
            ->search($request->search)
            ->filterByCategory($request->filter)
            ->filterByStatus($request->status === 'all' ? null : $request->status)
            ->sort($request->sort)
            ->paginate(10);

        $categories = TicketCategory::orderBy('category_name')->get();

        return [
            'tickets' => $tickets,
            'categories' => $categories,
            'statuses' => TicketStatus::cases(),
        ];
    }

    /**
     * Eagerly load all relationships and hierarchy details for ticket view.
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
     * Retrieve paginated endorsements with status metric tallies.
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
     * Load endorsement record details with alternate department options.
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
     * Load accomplishment record details including personnel and photo attachments.
     */
    public function getAccomplishmentDetails(TicketAccomplishment $accomplishment): TicketAccomplishment
    {
        return $accomplishment->load(['accomplishedBy', 'approvedBy', 'rejectedBy', 'photos']);
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

    // --- MUTATING METHODS ---

    /**
     * Persist a new consumer ticket along with initial status log and event dispatch.
     */
    public function createCwdTicket(array $validatedData): Ticket
    {
        if (! empty($validatedData['link_consumer']) && ! empty($validatedData['account_code'])) {
            $validatedData['consumer_id'] = $this->consumerService->resolveConsumerId($validatedData['account_code']);
        } else {
            $validatedData['consumer_id'] = null;
        }

        return DB::transaction(function () use ($validatedData) {
            $ticketNumber = $this->generateSequentialNumber();

            $ticket = Ticket::create(array_merge($validatedData, [
                'ticket_number' => $ticketNumber,
                'status' => TicketStatus::OPEN,
                'created_by' => Auth::id(),
                'reported_at' => now(),
            ]));

            $ticket->statusLog()->create([
                'changed_by' => Auth::id(),
                'old_status' => null,
                'new_status' => TicketStatus::OPEN,
            ]);

            TicketCreated::dispatch($ticket->load(['category', 'department']));

            return $ticket;
        });
    }

    /**
     * Process an endorsement decision (approval creates child ticket; rejection reverts parent status).
     */
    public function verifyEndorsement(array $validatedData, TicketEndorsement $endorsement): array
    {
        return DB::transaction(function () use ($validatedData, $endorsement) {

            // 1. Lock the exact endorsement record
            $lockedEndorsement = TicketEndorsement::where('id', $endorsement->id)
                ->lockForUpdate()
                ->first();

            // Race Condition Check
            if ($lockedEndorsement->status !== EndorsementStatus::PENDING) {
                return ['success' => false, 'message' => 'This endorsement has already been processed by another officer.'];
            }

            $isApproved = $validatedData['status'] === EndorsementStatus::APPROVED->value;

            // 2. Commit Endorsement Decision Updates
            $lockedEndorsement->update([
                'status' => $validatedData['status'],
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
                'rejection_reason' => $isApproved ? null : $validatedData['rejection_reason'],
            ]);

            // 3. Lock Parent Ticket dynamically via its relationship
            $parentTicket = $lockedEndorsement->ticket()->lockForUpdate()->first();

            if ($isApproved) {
                $oldParentStatus = $parentTicket->status;

                // Update the parent ticket's status field
                $parentTicket->update([
                    'status' => TicketStatus::ENDORSED,
                ]);

                // 4. Log the parent ticket's milestone with the actual state change
                $parentTicket->statusLog()->create([
                    'changed_by' => Auth::id(),
                    'old_status' => $oldParentStatus,
                    'new_status' => TicketStatus::ENDORSED,
                ]);

                // 5. Spawn child ticket (Inheriting all original form data from the parent)
                $childTicket = Ticket::create([
                    // System & Hierarchy
                    'ticket_number' => $this->generateChildTicketNumber($parentTicket),
                    'parent_ticket_id' => $parentTicket->getKey(),
                    'department_id' => $validatedData['department_id'],

                    // Inherited Consumer & Intake Data
                    'consumer_id' => $parentTicket->consumer_id,
                    'consumer_contact' => $parentTicket->consumer_contact,
                    'complaint_source' => $parentTicket->complaint_source,
                    'complaint_description' => $parentTicket->complaint_description,

                    // Inherited Categorization (Including Custom Options)
                    'category_id' => $parentTicket->category_id,
                    'other_category' => $parentTicket->other_category,
                    'other_category_name' => $parentTicket->other_category_name,

                    // Inherited Geographical Location
                    'purok' => $parentTicket->purok,
                    'street' => $parentTicket->street,
                    'barangay' => $parentTicket->barangay,
                    'landmark' => $parentTicket->landmark,

                    // New Ticket Metadata
                    'subject' => $this->generateChildTicketSubject($parentTicket),
                    'status' => TicketStatus::OPEN,
                    'created_by' => Auth::id(),
                    'reported_at' => now(),
                ]);

                // 6. Stamp initial log for child ticket
                $childTicket->statusLog()->create([
                    'changed_by' => Auth::id(),
                    'old_status' => null,
                    'new_status' => TicketStatus::OPEN,
                ]);

            } else {
                // Rejected Flow: Revert parent ticket
                $oldStatus = $parentTicket->status;

                $parentTicket->update([
                    'status' => $lockedEndorsement->pre_endorsement_status,
                ]);

                $parentTicket->statusLog()->create([
                    'changed_by' => Auth::id(),
                    'old_status' => $oldStatus,
                    'new_status' => $lockedEndorsement->pre_endorsement_status,
                ]);
            }

            return ['success' => true];
        });
    }

    /**
     * Manually spawn a child ticket linked directly to the parent ticket.
     */
    public function createManualChildTicket(Ticket $parentTicket, array $validatedData): Ticket
    {
        return DB::transaction(function () use ($parentTicket, $validatedData) {
            $lockedParent = Ticket::where('system_id', $parentTicket->system_id)
                ->lockForUpdate()
                ->firstOrFail();

            $childTicket = Ticket::create([
                'ticket_number' => $this->generateChildTicketNumber($lockedParent),
                'parent_ticket_id' => $lockedParent->system_id,
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
                'created_by' => Auth::id(),
                'reported_at' => now(),
            ]);

            $childTicket->statusLog()->create([
                'changed_by' => Auth::id(),
                'old_status' => null,
                'new_status' => TicketStatus::OPEN,
            ]);

            $lockedParent->remarks()->create([
                'user_id' => Auth::id(),
                'body' => "Child Ticket {$childTicket->ticket_number} was created.",
                'is_internal' => true,
            ]);

            TicketCreated::dispatch($childTicket->load(['category', 'department']));

            return $childTicket;
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
    private function generateChildTicketNumber(Ticket $parentTicket): string
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

    /**
     * Generate a unique sequential ticket tracking number for the current date.
     */
    private function generateSequentialNumber(): string
    {
        $dateCode = now()->format('ymd');

        $latestMatch = Ticket::where('ticket_number', 'like', "TKT-{$dateCode}-%")
            ->lockForUpdate()
            ->orderBy('ticket_number', 'desc')
            ->first();

        $nextSequence = 1;

        if ($latestMatch) {
            $lastAssignedDigits = (int) substr($latestMatch->ticket_number, -3);
            $nextSequence = $lastAssignedDigits + 1;
        }

        return sprintf('TKT-%s-%03d', $dateCode, $nextSequence);
    }
}
