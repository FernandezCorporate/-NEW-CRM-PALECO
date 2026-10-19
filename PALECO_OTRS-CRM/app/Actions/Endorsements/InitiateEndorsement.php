<?php

namespace App\Actions\Endorsements;

use App\Enums\EndorsementStatus;
use App\Enums\TicketStatus;
use App\Events\TicketEndorsementInitiated;
use App\Models\Ticket;
use App\Models\TicketEndorsement;
use App\Models\TicketStatusLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Single-Action: Initiates an endorsement request to another department, freezing ticket progress pending CWD review.
 */
class InitiateEndorsement
{
    /**
     * @throws ValidationException
     */
    public function handle(Ticket $ticket, array $data, User $supervisor): TicketEndorsement
    {
        return $this->execute($ticket, $data, $supervisor);
    }

    /**
     * @throws ValidationException
     */
    public function execute(Ticket $ticket, array $data, User $supervisor): TicketEndorsement
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

        return DB::transaction(function () use ($ticket, $data, $supervisor, $allowedStatuses) {
            $lockedTicket = Ticket::where('id', $ticket->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($lockedTicket->status, $allowedStatuses, true)) {
                throw ValidationException::withMessages([
                    'status' => 'Tickets that are resolved, closed, or locked in an endorsement workflow cannot be endorsed.',
                ]);
            }

            if ($lockedTicket->endorsements()->where('status', EndorsementStatus::PENDING->value)->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'This ticket already has a pending endorsement request.',
                ]);
            }

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

            DB::afterCommit(function () use ($endorsement) {
                $endorsement->load(['ticket', 'suggestedDepartment', 'creator']);
                rescue(fn () => event(new TicketEndorsementInitiated($endorsement)), report: true);
            });

            return $endorsement;
        });
    }
}

