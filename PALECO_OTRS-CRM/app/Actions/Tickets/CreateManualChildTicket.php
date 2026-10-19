<?php

namespace App\Actions\Tickets;

use App\Enums\TicketStatus;
use App\Events\TicketCreated;
use App\Models\Ticket;
use App\Services\Tickets\TicketSequenceService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Single-Action: Manually spawns a child ticket linked directly to the parent ticket.
 */
class CreateManualChildTicket
{
    public function __construct(
        protected TicketSequenceService $sequenceService
    ) {}

    public function handle(Ticket $parentTicket, array $validatedData): Ticket
    {
        return $this->execute($parentTicket, $validatedData);
    }

    public function execute(Ticket $parentTicket, array $validatedData): Ticket
    {
        $maxAttempts = 3;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $attempt++;

            try {
                return DB::transaction(function () use ($parentTicket, $validatedData) {
                    $lockedParent = Ticket::where('id', $parentTicket->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if (! is_null($lockedParent->parent_ticket_id)) {
                        throw ValidationException::withMessages([
                            'parent_ticket_id' => 'Cannot create a child ticket from another child ticket. Only root origin tickets can spawn child tickets.',
                        ]);
                    }

                    if ($lockedParent->status === TicketStatus::PENDING_ENDORSEMENT) {
                        throw ValidationException::withMessages([
                            'parent_ticket_id' => 'Cannot spawn child tickets from a ticket that is pending endorsement.',
                        ]);
                    }

                    if ($lockedParent->status === TicketStatus::CANCELLED) {
                        throw ValidationException::withMessages([
                            'parent_ticket_id' => 'Cannot spawn child tickets from a ticket that is cancelled.',
                        ]);
                    }

                    if ($lockedParent->status === TicketStatus::CLOSED) {
                        throw ValidationException::withMessages([
                            'parent_ticket_id' => 'Cannot spawn child tickets from a ticket that is closed.',
                        ]);
                    }

                    $childTicket = Ticket::create([
                        'ticket_number' => $this->sequenceService->generateChildTicketNumber($lockedParent),
                        'parent_ticket_id' => $lockedParent->id,
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
                        'created_by_id' => Auth::id(),
                        'reported_at' => now(),
                    ]);

                    $childTicket->statusLog()->create([
                        'changed_by_id' => Auth::id(),
                        'old_status' => null,
                        'new_status' => TicketStatus::OPEN,
                    ]);

                    $lockedParent->remarks()->create([
                        'user_id' => Auth::id(),
                        'body' => "Child Ticket {$childTicket->ticket_number} was created.",
                        'is_internal' => true,
                    ]);

                    // Defer broadcast until after database transaction commits
                    DB::afterCommit(function () use ($childTicket) {
                        rescue(fn () => TicketCreated::dispatch($childTicket->load(['category', 'department'])), report: true);
                    });

                    return $childTicket;
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

        throw new RuntimeException('Failed to generate a unique child ticket sequence number after multiple attempts.');
    }
}

