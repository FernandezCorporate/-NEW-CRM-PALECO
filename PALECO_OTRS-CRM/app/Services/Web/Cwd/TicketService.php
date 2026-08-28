<?php

namespace App\Services\Web\Cwd;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

use App\Enums\TicketStatus;
use App\Enums\ComplaintSources;
use App\Enums\EscalationStatus;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketEscalation;

/*
 * Encapsulates the core backend processing for Service Tickets.
 * Safeguards database integrity during concurrent ticket creation.
 */
class TicketService
{
    // --- CORE PROCESSES ---

    /*
     * Safely executes automated row tracking and ticket assignments inside a singular atomic transaction block.
     * Generates a sequential ID, creates the master record, and stamps the initial lifecycle log.
     */

    public function getTicketList(Request $request)
    {
        $status = TicketStatus::tryFrom((string) $request->status);

        $tickets = Ticket::with(['category', 'department', 'creator', 'parentTicket'])
            ->search($request->search)
            ->filterByCategory($request->filter)
            ->when($status, fn ($query) => $query->where('status', $status))
            ->sort($request->sort)
            ->paginate(10)
            ->withQueryString();
        
        $categories = TicketCategory::orderBy('category_name')->get();

        return [
            "tickets" => $tickets,
            "categories" => $categories,
            "statuses" => TicketStatus::cases(),
        ];
    }

    public function getTicketDetails(Ticket $ticket): array
    {
        $ticket->load([
            // Core & Routing
            'creator',
            'department.foremen',
            'team.members',
            'category',
            
            // Hierarchy
            'parentTicket.department',
            'childTickets.department',
            
            // History Modules
            'statusLog.updater',
            'assignments.team',
            'assignments.assigner',
            'escalations.suggestedDepartment',
            'escalations.creator',
            'escalations.reviewer',
            'accomplishments.accomplishedBy',
            'accomplishments.approvedBy',
            'accomplishments.rejectedBy'
        ]);

        return compact('ticket');
    }

    public function loadTicketForm()
    {
        $sources = ComplaintSources::cases();
        $categories = TicketCategory::orderBy('category_name')->get();
        $departments = Department::orderBy('dept_name')->get();

        return [
            "sources" => $sources,
            "categories" => $categories,
            "departments" => $departments
        ];
    }

    public function createCwdTicket(array $validatedData): Ticket
    {
        return DB::transaction(function () use ($validatedData) {
            
            // 1. Generate non-conflicting chronological number via sequential row locking patterns
            $ticketNumber = $this->generateSequentialNumber();

            // 2. Instantiate master repository layout
            $ticket = Ticket::create(array_merge($validatedData, [
                'ticket_number' => $ticketNumber,
                'status' => TicketStatus::OPEN,
                'created_by' => Auth::id(),
                'reported_at' => now(),
            ]));

            // 3. Populate matching relative entry tracking inside historic lifecycle modules
            $ticket->statusLog()->create([
                'changed_by' => Auth::id(),
                'old_status' => null,
                'new_status' => TicketStatus::OPEN,
            ]);

            return $ticket;
        });
    }

    public function getEscalationList(Request $request)
    {
        $escalations = TicketEscalation::with(['ticket', 'creator', 'suggestedDepartment'])
            ->search($request->search)
            ->filterByStatus($request->status)
            ->paginate(10)
            ->withQueryString();

        $statusMetrics = [
            'pending' => TicketEscalation::query()->where('status', EscalationStatus::PENDING)->count(),
            'denied' => TicketEscalation::query()->where('status', EscalationStatus::REJECTED)->count(),
            'escalated' => TicketEscalation::query()->where('status', EscalationStatus::APPROVED)->count() 
        ];

        $statuses = EscalationStatus::cases();

        return [
            "escalations" => $escalations,
            "statusMetrics" => $statusMetrics,
            "statuses" => $statuses
        ];
    }

    public function getEscalationDetails(TicketEscalation $escalation)
    {
        $escalation->load(['ticket', 'creator', 'suggestedDepartment']);

        $approveValue = EscalationStatus::APPROVED;
        $rejectValue = EscalationStatus::REJECTED;

        // Exclude the department currently handling the parent ticket
        $departments = Department::query()
            ->where('id', '!=', $escalation->ticket->department_id)
            ->get();

        return [
            "escalation" => $escalation,
            "approveValue" => $approveValue,
            "rejectValue" => $rejectValue,
            "departments" => $departments
        ];
    }

    public function verifyEscalation(array $validatedData, TicketEscalation $escalation)
    {
        return DB::transaction(function () use ($validatedData, $escalation) {
            
            // 1. Lock the exact escalation record
            $lockedEscalation = TicketEscalation::where('id', $escalation->id)
                ->lockForUpdate()
                ->first();

            // Race Condition Check
            if ($lockedEscalation->status !== EscalationStatus::PENDING) {
                return ['success' => false, 'message' => 'This escalation has already been processed by another officer.'];
            }

            $isApproved = $validatedData['status'] === EscalationStatus::APPROVED->value;

            // 2. Commit Escalation Decision Updates
            $lockedEscalation->update([
                'status' => $validatedData['status'],
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
                'rejection_reason' => $isApproved ? null : $validatedData['rejection_reason'],
            ]);

            // 3. Lock Parent Ticket dynamically via its relationship
            $parentTicket = $lockedEscalation->ticket()->lockForUpdate()->first();

            if ($isApproved) {
                // Track the old status before changing it
                $oldParentStatus = $parentTicket->status;
                
                // Update the parent ticket's status field
                // Note: Change TicketStatus::ESCALATED to match whatever your actual Enum name is
                $parentTicket->update([
                    'status' => TicketStatus::ESCALATED
                ]);
                
                // 4. Log the parent ticket's milestone with the actual state change
                $parentTicket->statusLog()->create([
                    'changed_by' => Auth::id(),
                    'old_status' => $oldParentStatus,
                    'new_status' => TicketStatus::ESCALATED, 
                ]);

                // 5. Spawn child ticket (Inheriting all original form data from the parent)
                $childTicket = Ticket::create([
                    // System & Hierarchy
                    'ticket_number'         => $this->generateChildTicketNumber($parentTicket),
                    'parent_ticket_id'      => $parentTicket->getKey(),
                    'department_id'         => $validatedData['department_id'], 
                    
                    // Inherited Consumer & Intake Data
                    'consumer_id'           => $parentTicket->consumer_id,
                    'complaint_source'      => $parentTicket->complaint_source, 
                    'complaint_description' => $parentTicket->complaint_description,
                    
                    // Inherited Categorization (Including Custom Options)
                    'category_id'           => $parentTicket->category_id,
                    'other_category'        => $parentTicket->other_category,
                    'other_category_name'   => $parentTicket->other_category_name,
                    
                    // Inherited Geographical Location
                    'purok'                 => $parentTicket->purok,
                    'street'                => $parentTicket->street,
                    'barangay'              => $parentTicket->barangay,
                    'landmark'              => $parentTicket->landmark,
                    
                    // New Ticket Metadata
                    'subject'               => $this->generateChildTicketSubject($parentTicket),
                    'status'                => TicketStatus::OPEN,
                    'created_by'            => Auth::id(), 
                    'reported_at'           => now(),
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
                    'status' => $lockedEscalation->pre_escalation_status
                ]);

                $parentTicket->statusLog()->create([
                    'changed_by' => Auth::id(),
                    'old_status' => $oldStatus,
                    'new_status' => $lockedEscalation->pre_escalation_status,
                ]);
            }

            return ['success' => true];
        });
    }

    private function generateChildTicketSubject(Ticket $parentTicket): string
    {
        return 'Escalated: ' . $parentTicket->subject;
    }

    /*
     * Generates a hierarchical suffix for escalated child tickets (e.g., TKT-260823-001-1).
     * Leverages row locking to prevent sequence collisions if multiple escalations happen simultaneously.
     */
    private function generateChildTicketNumber(Ticket $parentTicket): string
    {
        $baseNumber = $parentTicket->ticket_number;

        // Find the latest child ticket for this specific parent
        $latestChild = Ticket::where('ticket_number', 'like', "{$baseNumber}-%")
            ->lockForUpdate()
            ->orderBy('ticket_number', 'desc')
            ->first();

        $nextSequence = 1;

        if ($latestChild) {
            // Extract the current suffix (everything after the last dash) and increment
            $parts = explode('-', $latestChild->ticket_number);
            $lastAssignedDigits = (int) end($parts);
            $nextSequence = $lastAssignedDigits + 1;
        }

        return sprintf("%s-%d", $baseNumber, $nextSequence);
    }

    // --- PRIVATE HELPER METHODS ---

    /*
     * Computes safe, human-readable sequential indexing numbers.
     * Leverages explicit row locking (`lockForUpdate`) to completely prevent sequence overlap during concurrent submissions.
     */
    private function generateSequentialNumber(): string
    {
        $dateCode = now()->format('ymd'); // Format: YYMMDD
        
        // Block consecutive simultaneous threads using safe explicit exclusive row locking patterns
        $latestMatch = Ticket::where('ticket_number', 'like', "TKT-{$dateCode}-%")
            ->lockForUpdate()
            ->orderBy('ticket_number', 'desc')
            ->first();

        $nextSequence = 1;

        if ($latestMatch) {
            $lastAssignedDigits = (int) substr($latestMatch->ticket_number, -3);
            $nextSequence = $lastAssignedDigits + 1;
        }

        return sprintf("TKT-%s-%03d", $dateCode, $nextSequence);
    }
}
