<?php

namespace App\Actions\Accomplishments;

use App\Enums\TicketAccomplishmentStatus;
use App\Enums\TicketStatus;
use App\Events\TicketAccomplished;
use App\Models\Ticket;
use App\Models\TicketAccomplishment;
use App\Models\TicketStatusLog;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Single-Action: Submits a new accomplishment report with signature and photos, transitioning ticket to RESOLVED.
 */
class SubmitAccomplishment
{
    /**
     * @throws ValidationException
     * @throws Exception
     */
    public function handle(
        Ticket $ticket,
        User $worker,
        array $data,
        ?Carbon $clientTimestamp = null,
        ?string $idempotencyKey = null
    ): TicketAccomplishment {
        return $this->execute($ticket, $worker, $data, $clientTimestamp, $idempotencyKey);
    }

    /**
     * @throws ValidationException
     * @throws Exception
     */
    public function execute(
        Ticket $ticket,
        User $worker,
        array $data,
        ?Carbon $clientTimestamp = null,
        ?string $idempotencyKey = null
    ): TicketAccomplishment {
        // Idempotency safety: If client resubmits an accomplishment with the same key
        // (scoped to this ticket and worker so a reused key can never return another ticket's report)
        if ($idempotencyKey) {
            $existingReport = $ticket->accomplishments()
                ->where('accomplished_by_id', $worker->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existingReport) {
                return $existingReport->load(['accomplishedBy', 'approvedBy', 'rejectedBy', 'photos']);
            }
        }

        // Idempotency safety: If already resolved by this worker, return the existing report
        if ($ticket->status === TicketStatus::RESOLVED) {
            $existingReport = $ticket->accomplishments()
                ->where('accomplished_by_id', $worker->id)
                ->latest('id')
                ->first();
            // Only a still-pending report is a safe retry; an older rejected one would hide this new submission
            if ($existingReport && $existingReport->status === TicketAccomplishmentStatus::PENDING) {
                return $existingReport->load(['accomplishedBy', 'approvedBy', 'rejectedBy', 'photos']);
            }

            throw ValidationException::withMessages([
                'status' => 'This ticket already has a pending accomplishment report under supervisor review.',
            ]);
        }

        if ($ticket->status === TicketStatus::PENDING_ENDORSEMENT) {
            throw ValidationException::withMessages([
                'status' => 'Ticket is locked pending inter-departmental endorsement review.',
            ]);
        }

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

        $isOffline = $clientTimestamp !== null && $clientTimestamp->diffInMinutes(now()) > 5;
        $actualAccomplishedTime = $clientTimestamp ?? now();

        $disk = config('filesystems.default', 'public');
        $signaturePath = null;
        $photoPaths = [];

        try {
            if (isset($data['signature'])) {
                $signaturePath = $data['signature']->store('accomplishments/signatures', $disk);
            }

            return DB::transaction(function () use (
                $ticket,
                $worker,
                $data,
                $disk,
                $signaturePath,
                &$photoPaths,
                $actualAccomplishedTime,
                $clientTimestamp,
                $isOffline,
                $idempotencyKey
            ) {
                $lockedTicket = Ticket::where('id', $ticket->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // Concurrency safety under pessimistic row lock
                if ($lockedTicket->status === TicketStatus::RESOLVED) {
                    $existingReport = $lockedTicket->accomplishments()
                        ->where('accomplished_by_id', $worker->id)
                        ->latest('id')
                        ->first();
                    if ($existingReport && $existingReport->status === TicketAccomplishmentStatus::PENDING) {
                        return $existingReport->load(['accomplishedBy', 'approvedBy', 'rejectedBy', 'photos']);
                    }

                    throw ValidationException::withMessages([
                        'status' => 'This ticket already has a pending accomplishment report under supervisor review.',
                    ]);
                }

                if ($lockedTicket->status === TicketStatus::PENDING_ENDORSEMENT) {
                    throw ValidationException::withMessages([
                        'status' => 'Ticket is locked pending inter-departmental endorsement review.',
                    ]);
                }

                if ($lockedTicket->status !== TicketStatus::IN_PROGRESS) {
                    throw ValidationException::withMessages([
                        'status' => 'Only tickets that are currently in progress can be marked as accomplished.',
                    ]);
                }

                if ($lockedTicket->accomplishments()->where('status', TicketAccomplishmentStatus::PENDING)->exists()) {
                    throw ValidationException::withMessages([
                        'status' => 'This ticket already has a pending accomplishment report under supervisor review.',
                    ]);
                }

                $oldStatus = $lockedTicket->status;

                $report = $lockedTicket->accomplishments()->create([
                    'accomplished_by_id' => $worker->id,
                    'remarks' => $data['remarks'],
                    'accomplished_at' => $actualAccomplishedTime,
                    'client_accomplished_at' => $clientTimestamp,
                    'consumer_name' => $data['consumer_name'] ?? null,
                    'signature_path' => $signaturePath,
                    'status' => TicketAccomplishmentStatus::PENDING,
                    'is_offline_synced' => $isOffline,
                    'synced_at' => $isOffline ? now() : null,
                    'idempotency_key' => $idempotencyKey,
                ]);

                if (! empty($data['photos'])) {
                    foreach ($data['photos'] as $photo) {
                        $path = $photo->store('accomplishments/photos', $disk);
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
                    'created_at' => $actualAccomplishedTime,
                ]);

                $ticketUpdate = [
                    'status' => TicketStatus::RESOLVED,
                    'resolved_at' => $actualAccomplishedTime,
                ];

                if ($isOffline && ! $lockedTicket->is_offline_synced) {
                    $ticketUpdate['is_offline_synced'] = true;
                    $ticketUpdate['synced_at'] = now();
                }

                $lockedTicket->update($ticketUpdate);

                $freshTicket = $lockedTicket->fresh(['category', 'team', 'department']);

                DB::afterCommit(function () use ($freshTicket) {
                    rescue(fn () => TicketAccomplished::dispatch($freshTicket), report: true);
                });

                return $report;
            });
        } catch (Exception $e) {
            if ($signaturePath) {
                Storage::disk($disk)->delete($signaturePath);
            }
            if (! empty($photoPaths)) {
                Storage::disk($disk)->delete($photoPaths);
            }

            throw $e;
        }
    }
}

