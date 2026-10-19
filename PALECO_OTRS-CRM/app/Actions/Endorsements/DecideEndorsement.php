<?php

namespace App\Actions\Endorsements;

use App\Enums\EndorsementStatus;
use App\Enums\TicketStatus;
use App\Events\TicketCreated;
use App\Events\TicketEndorsementDecided;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketEndorsement;
use App\Services\Tickets\TicketSequenceService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Single-Action: Process an endorsement decision (approval creates child ticket & closes parent assignment; rejection reverts parent status).
 */
class DecideEndorsement
{
    public function __construct(
        protected TicketSequenceService $sequenceService
    ) {}

    public function handle(array $validatedData, TicketEndorsement $endorsement): array
    {
        return $this->execute($validatedData, $endorsement);
    }

    public function execute(array $validatedData, TicketEndorsement $endorsement): array
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
                            'ticket_number' => $this->sequenceService->generateChildTicketNumber($parentTicket),
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

                            'subject' => 'Endorsed: '.$parentTicket->subject,
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
}

