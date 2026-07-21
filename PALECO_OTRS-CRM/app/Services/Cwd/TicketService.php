<?php

namespace App\Services\Cwd;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use App\Enums\TicketStatus;
use App\Models\Ticket;

/*
 * Encapsulates the core backend processing for Service Tickets.
 * Safeguards database integrity during concurrent ticket creation.
 */
class TicketService
{
    // --- CORE PROCESSES ---

    /*
     * Safely executes automated row tracking and ticket assignments inside a singular atomic transaction block.
     * Generates a sequential ID, creates the master record, and stamps the initial lifecycle log.
     */
    public function createCwdTicket(array $validatedData): Ticket
    {
        return DB::transaction(function () use ($validatedData) {
            
            // 1. Generate non-conflicting chronological number via sequential row locking patterns
            $ticketNumber = $this->generateSequentialNumber();

            // 2. Instantiate master repository layout
            $ticket = Ticket::create(array_merge($validatedData, [
                'ticket_number' => $ticketNumber,
                'status' => TicketStatus::OPEN,
                'created_by' => Auth::id(),
                'reported_at' => now(),
            ]));

            // 3. Populate matching relative entry tracking inside historic lifecycle modules
            $ticket->statusLog()->create([
                'changed_by' => Auth::id(),
                'old_status' => null,
                'new_status' => TicketStatus::OPEN,
            ]);

            return $ticket;
        });
    }

    // --- PRIVATE HELPER METHODS ---

    /*
     * Computes safe, human-readable sequential indexing numbers.
     * Leverages explicit row locking (`lockForUpdate`) to completely prevent sequence overlap during concurrent submissions.
     */
    private function generateSequentialNumber(): string
    {
        $dateCode = now()->format('ymd'); // Format: YYMMDD
        
        // Block consecutive simultaneous threads using safe explicit exclusive row locking patterns
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