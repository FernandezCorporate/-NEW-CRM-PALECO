<?php

namespace App\Actions\Tickets;

use App\Enums\TicketStatus;
use App\Events\TicketCreated;
use App\Models\Ticket;
use App\Services\External\PalecoBillingApiService;
use App\Services\Tickets\TicketSequenceService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Single-Action: Registers a new CWD consumer ticket along with initial status log and deferred broadcast event.
 */
class CreateCwdTicket
{
    public function __construct(
        protected PalecoBillingApiService $billingApiService,
        protected TicketSequenceService $sequenceService
    ) {}

    public function handle(array $validatedData): Ticket
    {
        return $this->execute($validatedData);
    }

    public function execute(array $validatedData): Ticket
    {
        if (! empty($validatedData['link_consumer']) && ! empty($validatedData['account_code'])) {
            $fallbackData = [
                'acct_code' => $validatedData['account_code'],
                'name' => $validatedData['consumer_name'] ?? null,
                'acct_no' => $validatedData['consumer_no'] ?? null,
                'meter_serial' => $validatedData['consumer_meter'] ?? null,
                'address' => $validatedData['consumer_address'] ?? null,
                'status' => $validatedData['consumer_status'] ?? null,
            ];
            $validatedData['consumer_id'] = $this->billingApiService->resolveConsumerId($validatedData['account_code'], $fallbackData);
        } else {
            $validatedData['consumer_id'] = null;
        }

        unset(
            $validatedData['link_consumer'],
            $validatedData['account_code'],
            $validatedData['consumer_name'],
            $validatedData['consumer_no'],
            $validatedData['consumer_meter'],
            $validatedData['consumer_address'],
            $validatedData['consumer_status']
        );

        $maxAttempts = 3;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $attempt++;

            try {
                return DB::transaction(function () use ($validatedData) {
                    $ticketNumber = $this->sequenceService->generateSequentialNumber();

                    $ticket = Ticket::create(array_merge($validatedData, [
                        'ticket_number' => $ticketNumber,
                        'status' => TicketStatus::OPEN,
                        'created_by_id' => Auth::id(),
                        'reported_at' => now(),
                    ]));

                    $ticket->statusLog()->create([
                        'changed_by_id' => Auth::id(),
                        'old_status' => null,
                        'new_status' => TicketStatus::OPEN,
                    ]);

                    // Defer broadcast until after database transaction commits
                    DB::afterCommit(function () use ($ticket) {
                        rescue(fn () => TicketCreated::dispatch($ticket->load(['category', 'department'])), report: true);
                    });

                    return $ticket;
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

        throw new RuntimeException('Failed to generate a unique ticket sequence number after multiple attempts.');
    }
}

