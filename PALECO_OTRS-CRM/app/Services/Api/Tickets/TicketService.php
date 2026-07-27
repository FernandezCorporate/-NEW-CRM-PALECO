<?php

namespace App\Services\Api\Tickets;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\TicketStatusLog;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/*
 * Encapsulates the core ticket retrieval and operational logic for the mobile API.
 * Enforces role-based scoping and transactional database updates.
 */
class TicketService
{
    public function getInboxTickets(User $user, array $params): LengthAwarePaginator
    {
        $query = Ticket::query()
            ->with('category')
            ->withCount('childTickets');

        if ($user->role->slug_identifier === 'foreman') {
            $query->where('department_id', $user->department_id);
        } elseif ($user->role->slug_identifier === 'field_personnel') {
            $teamIds = $user->teams()->pluck('teams.id');
            $query->whereIn('team_id', $teamIds);
        }

        $query->apiSearch($params['search'] ?? null)
              ->apiFilterByCategoryName($params['category'] ?? null)
              ->apiFilterByStatus($params['status'] ?? null)
              ->apiSort($params['sort'] ?? null);

        return $query->paginate(10);
    }

    // --- MUTATING METHODS ---

    public function assignTicket(Ticket $ticket, string $teamId, User $assigner): Ticket
    {
        return DB::transaction(function () use ($ticket, $teamId, $assigner) {
            
            // 1. Capture the current status
            $oldStatus = $ticket->status;

            // 2. Close out any active previous assignment
            $ticket->assignments()
                   ->whereNull('unassigned_at')
                   ->update(['unassigned_at' => now()]);

            // 3. Create the new SLA tracking log
            TicketAssignment::create([
                'ticket_id'   => $ticket->system_id,
                'team_id'     => $teamId,
                'assigned_by' => $assigner->id,
            ]);

            // 4. LOGIC FIX: Only log the state transition if the status actually changed
            // If it's a reassignment, $oldStatus will already be ASSIGNED, so this skips the useless log.
            if ($oldStatus !== TicketStatus::ASSIGNED) {
                TicketStatusLog::create([
                    'ticket_id'  => $ticket->system_id,
                    'old_status' => $oldStatus, 
                    'new_status' => TicketStatus::ASSIGNED,
                    'changed_by' => $assigner->id,
                ]);
            }

            // 5. Update the core ticket state
            $ticket->update([
                'team_id' => $teamId,
                'status'  => TicketStatus::ASSIGNED,
            ]);

            // 6. Return fresh model for the API response
            return $ticket->fresh(['category']);
        });
    }
}