<?php

namespace App\Services\Web\Cwd;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

use App\Enums\TicketStatus;
use App\Enums\ComplaintSources;
use App\Enums\EndorsementStatus;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketAccomplishment;
use App\Models\TicketCategory;
use App\Models\TicketEndorsement;
use App\Services\External\ConsumerService;
use App\Events\TicketCreated;

/*
 * Encapsulates the core backend processing for Service Tickets.
 * Safeguards database integrity during concurrent ticket creation.
 */
class TicketService
{
    public function __construct(protected ConsumerService $consumerService) {}

    // --- CORE PROCESSES ---

    public function getTicketList(Request $request)
    {
        $tickets = Ticket::with(['category', 'department', 'creator', 'parentTicket'])
            ->search($request->search)
            ->filterByCategory($request->filter)
            ->filterByStatus($request->status === 'all' ? null : $request->status)
            ->sort($request->sort)
            ->paginate(10);
        
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
        if (!empty($validatedData['link_consumer']) && !empty($validatedData['account_code'])) {
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

    public function getEndorsementList(Request $request)
    {
        $endorsements = TicketEndorsement::with(['ticket', 'creator', 'suggestedDepartment'])
            ->search($request->search)
            ->filterByStatus($request->status)
            ->paginate(10)
            ->withQueryString();

        $statusMetrics = [
            'pending' => TicketEndorsement::query()->where('status', EndorsementStatus::PENDING)->count(),
            'denied' => TicketEndorsement::query()->where('status', EndorsementStatus::REJECTED)->count(),
            'endorsed' => TicketEndorsement::query()->where('status', EndorsementStatus::APPROVED)->count() 
        ];

        $statuses = EndorsementStatus::cases();

        return [
            "endorsements" => $endorsements,
            "statusMetrics" => $statusMetrics,
            "statuses" => $statuses
        ];
    }

    public function getEndorsementDetails(TicketEndorsement $endorsement)
    {
        $endorsement->load(['ticket', 'creator', 'suggestedDepartment']);

        $approveValue = EndorsementStatus::APPROVED;
        $rejectValue = EndorsementStatus::REJECTED;

        $departments = Department::query()
            ->where('id', '!=', $endorsement->ticket->department_id)
            ->get();

        return [
            "endorsement" => $endorsement,
            "approveValue" => $approveValue,
            "rejectValue" => $rejectValue,
            "departments" => $departments
        ];
    }

    public function verifyEndorsement(array $validatedData, TicketEndorsement $endorsement)
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
                    'status' => TicketStatus::ENDORSED
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
                    'status' => $lockedEndorsement->pre_endorsement_status
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

    public function getAccomplishmentDetails(TicketAccomplishment $accomplishment)
    {
        return $accomplishment->load(['accomplishedBy', 'approvedBy', 'rejectedBy', 'photos']);
    }

    private function generateChildTicketSubject(Ticket $parentTicket): string
    {
        return 'Endorsed: ' . $parentTicket->subject;
    }

    private function generateChildTicketNumber(Ticket $parentTicket): string
    {
        $baseNumber = $parentTicket->ticket_number;

        $latestChild = Ticket::where('ticket_number', 'like', "{$baseNumber}-%")
            ->lockForUpdate()
            ->orderBy('ticket_number', 'desc')
            ->first();

        $nextSequence = 1;

        if ($latestChild) {
            $parts = explode('-', $latestChild->ticket_number);
            $lastAssignedDigits = (int) end($parts);
            $nextSequence = $lastAssignedDigits + 1;
        }

        return sprintf("%s-%d", $baseNumber, $nextSequence);
    }

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

        return sprintf("TKT-%s-%03d", $dateCode, $nextSequence);
    }
}