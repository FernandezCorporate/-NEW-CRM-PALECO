<?php

namespace App\Services\Tickets;

use App\Enums\TicketAccomplishmentStatus;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketAccomplishment;
use App\Models\TicketStatusLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Encapsulates the complete accomplishment report lifecycle:
 * photo uploads, work verification, supervisory review, and assignment finalization.
 */
class TicketAccomplishmentService
{
    // --- QUERY METHODS ---

    /**
     * Retrieves all accomplishment reports submitted for a ticket.
     */
    public function getAccomplishments(Ticket $ticket): Collection
    {
        return $ticket->accomplishments()
            ->with(['accomplishedBy', 'approvedBy', 'rejectedBy', 'photos'])
            ->latest('accomplished_at')
            ->get();
    }

    /**
     * Eagerly loads relationships for detailed accomplishment inspection.
     * Supports single-parameter (Web) and two-parameter (Api) invocations.
     */
    public function getAccomplishmentDetails(Ticket|TicketAccomplishment $first, ?TicketAccomplishment $second = null): TicketAccomplishment
    {
        $accomplishment = $second ?? $first;

        if ($first instanceof Ticket && $second instanceof TicketAccomplishment) {
            if ($second->ticket_id !== $first->id) {
                abort(404, 'This accomplishment report does not belong to the requested ticket.');
            }
        }

        return $accomplishment->load(['accomplishedBy', 'approvedBy', 'rejectedBy', 'photos']);
    }

    // --- MUTATING METHODS ---

    /**
     * Submits a new accomplishment report with signature and photos, transitioning ticket to RESOLVED.
     *
     * @throws ValidationException
     */
    public function accomplishTicket(Ticket $ticket, User $worker, array $data): TicketAccomplishment
    {
        if ($ticket->status !== TicketStatus::IN_PROGRESS) {
            throw ValidationException::withMessages([
                'status' => 'Only tickets that are currently in progress can be marked as accomplished.',
            ]);
        }

        if ($ticket->accomplishments()->where('status', TicketAccomplishmentStatus::PENDING)->exists()) {
            throw ValidationException::withMessages([
                'status' => 'This ticket already has a pending accomplishment report under supervisor review.',
            ]);
        }

        $signaturePath = null;
        $photoPaths = [];

        try {
            if (isset($data['signature'])) {
                $signaturePath = $data['signature']->store('accomplishments/signatures', 'public');
            }

            return DB::transaction(function () use ($ticket, $worker, $data, $signaturePath, &$photoPaths) {
                $lockedTicket = Ticket::where('id', $ticket->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $oldStatus = $lockedTicket->status;

                $report = $lockedTicket->accomplishments()->create([
                    'accomplished_by_id' => $worker->id,
                    'remarks' => $data['remarks'],
                    'accomplished_at' => now(),
                    'consumer_name' => $data['consumer_name'] ?? null,
                    'signature_path' => $signaturePath,
                    'status' => TicketAccomplishmentStatus::PENDING,
                ]);

                if (! empty($data['photos'])) {
                    foreach ($data['photos'] as $photo) {
                        $path = $photo->store('accomplishments/photos', 'public');
                        $photoPaths[] = $path;

                        $report->photos()->create([
                            'file_path' => $path,
                            'file_name' => $photo->getClientOriginalName(),
                            'file_size' => $photo->getSize(),
                            'mime_type' => $photo->getMimeType(),
                        ]);
                    }
                }

                TicketStatusLog::create([
                    'ticket_id' => $lockedTicket->id,
                    'old_status' => $oldStatus,
                    'new_status' => TicketStatus::RESOLVED,
                    'changed_by_id' => $worker->id,
                ]);

                $lockedTicket->update([
                    'status' => TicketStatus::RESOLVED,
                    'resolved_at' => now(),
                ]);

                return $report;
            });
        } catch (\Exception $e) {
            if ($signaturePath) {
                Storage::disk('public')->delete($signaturePath);
            }
            if (! empty($photoPaths)) {
                Storage::disk('public')->delete($photoPaths);
            }

            throw $e;
        }
    }

    /**
     * Evaluates a submitted accomplishment report (approves and closes ticket or rejects back to in-progress).
     *
     * @throws ValidationException
     */
    public function verifyAccomplishment(Ticket $ticket, TicketAccomplishment $accomplishment, array $data, User $supervisor): TicketAccomplishment
    {
        if ($accomplishment->ticket_id !== $ticket->id) {
            abort(404, 'This accomplishment report does not belong to the requested ticket.');
        }

        return DB::transaction(function () use ($ticket, $accomplishment, $data, $supervisor) {
            $lockedAccomplishment = TicketAccomplishment::where('id', $accomplishment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedAccomplishment->status !== TicketAccomplishmentStatus::PENDING) {
                throw ValidationException::withMessages([
                    'status' => 'This accomplishment report has already been evaluated.',
                ]);
            }

            $lockedTicket = Ticket::where('id', $ticket->id)
                ->lockForUpdate()
                ->firstOrFail();

            $oldTicketStatus = $lockedTicket->status;

            if ($data['status'] === TicketAccomplishmentStatus::APPROVED->value) {
                $lockedAccomplishment->update([
                    'status' => TicketAccomplishmentStatus::APPROVED,
                    'approved_by_id' => $supervisor->id,
                ]);

                // Close active assignment since the ticket's field lifecycle is completely finalized
                $lockedTicket->assignments()
                    ->whereNull('unassigned_at')
                    ->update(['unassigned_at' => now()]);

                $lockedTicket->update([
                    'status' => TicketStatus::CLOSED,
                    'closed_at' => now(),
                ]);

                TicketStatusLog::create([
                    'ticket_id' => $lockedTicket->id,
                    'old_status' => $oldTicketStatus,
                    'new_status' => TicketStatus::CLOSED,
                    'changed_by_id' => $supervisor->id,
                ]);
            }

            if ($data['status'] === TicketAccomplishmentStatus::REJECTED->value) {
                $lockedAccomplishment->update([
                    'status' => TicketAccomplishmentStatus::REJECTED,
                    'rejection_reason' => $data['rejection_reason'],
                    'rejected_by_id' => $supervisor->id,
                ]);

                $lockedTicket->update([
                    'status' => TicketStatus::IN_PROGRESS,
                    'resolved_at' => null,
                ]);

                TicketStatusLog::create([
                    'ticket_id' => $lockedTicket->id,
                    'old_status' => $oldTicketStatus,
                    'new_status' => TicketStatus::IN_PROGRESS,
                    'changed_by_id' => $supervisor->id,
                ]);
            }

            return $lockedAccomplishment->fresh(['accomplishedBy', 'rejectedBy', 'approvedBy', 'photos']);
        });
    }
}
