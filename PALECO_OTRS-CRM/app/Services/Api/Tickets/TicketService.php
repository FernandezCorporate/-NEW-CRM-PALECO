<?php

namespace App\Services\Api\Tickets;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\TicketStatusLog;
use App\Models\User;
use App\Models\Team;
use App\Models\Department;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use App\Enums\TicketAccomplishmentStatus;
use App\Models\TicketAccomplishment;
use App\Models\TicketEscalation;
use App\Enums\EscalationStatus;
use Illuminate\Support\Facades\Storage;

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

        return $query->paginate(10)->withQueryString();
    }

    public function getTicketStatusCount(User $user): array
    {
        $baseQuery = Ticket::query();

        if ($user->role->slug_identifier === 'foreman') {
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
            'pending_escalation' => (clone $baseQuery)->where('status', TicketStatus::PENDING_ESCALATION)->count(),
            'escalated' => (clone $baseQuery)->where('status', TicketStatus::ESCALATED)->count(),
        ];
    }

    // --- MUTATING METHODS ---

    public function assignTicket(Ticket $ticket, string $teamId, User $assigner, ?string $reason = null): Ticket
    {
        return DB::transaction(function () use ($ticket, $teamId, $assigner, $reason) {
            
            $oldStatus = $ticket->status;

            $ticket->assignments()
                   ->whereNull('unassigned_at')
                   ->update(['unassigned_at' => now()]);

            TicketAssignment::create([
                'ticket_id'   => $ticket->system_id,
                'team_id'     => $teamId,
                'assigned_by' => $assigner->id,
                'reason'      => $reason,
            ]);

            if ($oldStatus !== TicketStatus::ASSIGNED) {
                TicketStatusLog::create([
                    'ticket_id'  => $ticket->system_id,
                    'old_status' => $oldStatus, 
                    'new_status' => TicketStatus::ASSIGNED,
                    'changed_by' => $assigner->id,
                ]);
            }

            $ticket->update([
                'team_id' => $teamId,
                'status'  => TicketStatus::ASSIGNED,
            ]);

            return $ticket->fresh(['category']);
        });
    }

    public function startTicket(Ticket $ticket, User $worker): Ticket
    {
        return DB::transaction(function () use ($ticket, $worker) {
            
            $oldStatus = $ticket->status;

            TicketStatusLog::create([
                'ticket_id'  => $ticket->system_id,
                'old_status' => $oldStatus, 
                'new_status' => TicketStatus::IN_PROGRESS,
                'changed_by' => $worker->id,
            ]);

            $ticket->update([
                'status'     => TicketStatus::IN_PROGRESS,
                'started_at' => now(),
            ]);

            return $ticket->fresh(['category']);
        });
    }

    public function accomplishTicket(Ticket $ticket, User $worker, array $accomplishmentDetails): TicketAccomplishment
    {
        // Initialize variables outside the transaction so the catch block can access them
        $signaturePath = null;
        $photoPaths = [];

        try {
            return DB::transaction(function () use ($ticket, $worker, $accomplishmentDetails, &$signaturePath, &$photoPaths) {
                
                // 1. Physically store the files and track their paths in the external variables
                $signaturePath = $accomplishmentDetails['signature']->store("accomplishments/{$ticket->system_id}/signature", 'public');
                
                foreach ($accomplishmentDetails['photos'] as $photo) {
                    $photoPaths[] = $photo->store("accomplishments/{$ticket->system_id}/photos", 'public');
                }

                // 2. Map and insert database records
                $report = TicketAccomplishment::create([
                    'ticket_id'          => $ticket->system_id,
                    'accomplished_by_id' => $worker->id, 
                    'remarks'            => $accomplishmentDetails['remarks'],
                    'consumer_name'      => $accomplishmentDetails['consumer_name'] ?? null,
                    'signature_path'     => $signaturePath,
                    'status'             => TicketAccomplishmentStatus::PENDING,
                    'accomplished_at'    => now(), 
                ]);

                // Map the simple array of paths into the associative array createMany expects
                $photoRecords = array_map(fn($path) => ['file_path' => $path], $photoPaths);
                $report->photos()->createMany($photoRecords);

                // 3. Status transitions
                TicketStatusLog::create([
                    'ticket_id'  => $ticket->system_id,
                    'old_status' => $ticket->status, 
                    'new_status' => TicketStatus::RESOLVED,
                    'changed_by' => $worker->id,
                ]);

                $ticket->update([
                    'status'      => TicketStatus::RESOLVED,
                    'resolved_at' => now(),
                ]);

                return $report;
            });

        } catch (\Exception $e) {
            // 4. The Failsafe: Erase the physical files if the database transaction fails
            if ($signaturePath) {
                Storage::disk('public')->delete($signaturePath);
            }
            if (!empty($photoPaths)) {
                Storage::disk('public')->delete($photoPaths);
            }
            
            // Re-throw the error so the controller handles the failure properly
            throw $e;
        }
    }

    // --- QUERY METHODS ---

    public function getAccomplishmentDetails(TicketAccomplishment $accomplishment): TicketAccomplishment
    {
        return $accomplishment->load([
            'accomplishedBy',
            'rejectedBy',
            'approvedBy',
        ]);
    }

    public function verifyAccomplishment(Ticket $ticket, TicketAccomplishment $accomplishment, array $data, User $foreman): TicketAccomplishment
    {
        return DB::transaction(function () use ($ticket, $accomplishment, $data, $foreman) {
            
            $oldTicketStatus = $ticket->status;

            if ($data['status'] === TicketAccomplishmentStatus::APPROVED->value) {
                $accomplishment->update([
                    'status' => TicketAccomplishmentStatus::APPROVED,
                    'approved_by_id' => $foreman->id,
                ]);

                $ticket->update([
                    'status' => TicketStatus::CLOSED,
                    'closed_at' => now(),
                ]);

                TicketStatusLog::create([
                    'ticket_id'  => $ticket->system_id,
                    'old_status' => $oldTicketStatus, 
                    'new_status' => TicketStatus::CLOSED,
                    'changed_by' => $foreman->id,
                ]);
            } 
            
            if ($data['status'] === TicketAccomplishmentStatus::REJECTED->value) {
                $accomplishment->update([
                    'status' => TicketAccomplishmentStatus::REJECTED,
                    'rejection_reason' => $data['rejection_reason'],
                    'rejected_by_id' => $foreman->id,
                ]);

                $ticket->update([
                    'status' => TicketStatus::IN_PROGRESS,
                    'resolved_at' => null, 
                ]);

                TicketStatusLog::create([
                    'ticket_id'  => $ticket->system_id,
                    'old_status' => $oldTicketStatus, 
                    'new_status' => TicketStatus::IN_PROGRESS,
                    'changed_by' => $foreman->id,
                ]);
            }
            return $accomplishment->fresh(['accomplishedBy', 'rejectedBy', 'approvedBy']);
        });
    }

    public function requestEscalation(Ticket $ticket, array $data, User $foreman): TicketEscalation
    {
        return DB::transaction(function () use ($ticket, $data, $foreman) {
            
            // 1. Create the escalation record
            $escalation = $ticket->escalations()->create([
                'created_by'              => $foreman->id,
                'suggested_department_id' => $data['suggested_department_id'] ?? null,
                'reason'                  => $data['reason'],
                'pre_escalation_status'   => $ticket->status->value,
                'status'                  => EscalationStatus::PENDING,
            ]);

            // 2. Freeze the parent ticket
            $ticket->update([
                'status' => TicketStatus::PENDING_ESCALATION,
            ]);

            return $escalation;
        });
    }

    public function getDetailedTicketMobile(Ticket $ticket): Ticket
    {
        return $ticket->load([
            'category',
            'creator.role',
            'team.members',
            'statusLog.updater',
            'childTickets'
        ])->loadCount('childTickets');
    }

    public function getAssignOptions(User $foreman, Ticket $ticket)
    {
        $teams = Team::query()
            ->where('department_id', $foreman->department_id)
            ->withCount([
                'members', 
                'ticket' => function ($query) use ($ticket) {
                    $query->whereIn('status', [
                        TicketStatus::ASSIGNED,
                        TicketStatus::IN_PROGRESS,
                    ]);
                }])
            ->get();

        $teams->each(function ($team) use ($ticket) {
            $team->is_current = $team->id === $ticket->team_id;
        });

        return $teams;
    }

    public function getEscalationOptions(User $foreman)
    {
        $departments  = Department::query()->get();

        $departments->each(function ($department) use ($foreman) {
            $department->is_current = $department->id === $foreman->department_id;
        });

        return $departments;
    }

    public function getAccomplishments(Ticket $ticket)
    {
        $accomplishments = $ticket->accomplishments()
            ->with(['accomplishedBy', 'rejectedBy', 'approvedBy'])
            ->latest('accomplished_at')
            ->get();

        return $accomplishments;
    }

    public function viewTicketHistory(Ticket $ticket)
    {
        $ticketHistory = $ticket->load([
            'assignments.team', 
            'assignments.assigner.role',
            'escalations.suggestedDepartment',
            'escalations.creator.role',
            'escalations.reviewer'
        ]);

        return $ticketHistory;
    }
}