<?php

namespace App\Actions\Tickets;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Services\External\PalecoBillingApiService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Single-Action: Updates clerical intake details for an Open ticket.
 */
class UpdateTicketIntake
{
    public function __construct(
        protected PalecoBillingApiService $billingApiService
    ) {}

    /**
     * @return array{success: bool, changed: bool, ticket: Ticket}
     */
    public function handle(Ticket $ticket, array $validatedData): array
    {
        return $this->execute($ticket, $validatedData);
    }

    /**
     * @return array{success: bool, changed: bool, ticket: Ticket}
     */
    public function execute(Ticket $ticket, array $validatedData): array
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

        if (! empty($validatedData['other_category'])) {
            $validatedData['category_id'] = null;
        } else {
            $validatedData['other_category_name'] = null;
        }

        return DB::transaction(function () use ($ticket, $validatedData) {
            $lockedTicket = Ticket::where('id', $ticket->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedTicket->status !== TicketStatus::OPEN) {
                throw ValidationException::withMessages([
                    'status' => 'This ticket is no longer in Open status (it may have been assigned to a crew or processed concurrently) and can no longer be edited.',
                ]);
            }

            $lockedTicket->fill($validatedData);

            if ($lockedTicket->isClean()) {
                return [
                    'success' => true,
                    'changed' => false,
                    'ticket' => $lockedTicket,
                ];
            }

            $lockedTicket->save();

            if (Auth::check()) {
                $lockedTicket->remarks()->create([
                    'user_id' => Auth::id(),
                    'body' => 'Clerical intake details updated.',
                    'is_internal' => true,
                ]);
            }

            return [
                'success' => true,
                'changed' => true,
                'ticket' => $lockedTicket,
            ];
        });
    }
}

