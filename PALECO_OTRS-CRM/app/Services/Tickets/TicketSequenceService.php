<?php

namespace App\Services\Tickets;

use App\Models\Ticket;

/**
 * Encapsulates sequential ticket number generation with atomic row locking and concurrency controls.
 */
class TicketSequenceService
{
    /**
     * Generate a unique sequential ticket tracking number for the current date.
     * Restricts search strictly to root tickets (parent_ticket_id is null) to prevent
     * child ticket numbers (e.g. TKT-261007-005-1) from contaminating sequence arithmetic.
     */
    public function generateSequentialNumber(): string
    {
        $dateCode = now()->format('ymd');

        $latestMatch = Ticket::where('ticket_number', 'like', "TKT-{$dateCode}-%")
            ->whereNull('parent_ticket_id')
            ->lockForUpdate()
            ->orderBy('ticket_number', 'desc')
            ->first();

        $nextSequence = 1;

        if ($latestMatch) {
            $parts = explode('-', $latestMatch->ticket_number);
            $lastAssignedDigits = (int) end($parts);
            $nextSequence = $lastAssignedDigits + 1;
        }

        return sprintf('TKT-%s-%03d', $dateCode, $nextSequence);
    }

    /**
     * Generate a sequential child ticket number matching parent's pattern.
     */
    public function generateChildTicketNumber(Ticket $parentTicket): string
    {
        $baseNumber = $parentTicket->ticket_number;

        $existingChildren = Ticket::where('ticket_number', 'like', "{$baseNumber}-%")
            ->lockForUpdate()
            ->pluck('ticket_number');

        $maxSequence = 0;
        foreach ($existingChildren as $childNumber) {
            $parts = explode('-', $childNumber);
            $suffix = (int) end($parts);
            if ($suffix > $maxSequence) {
                $maxSequence = $suffix;
            }
        }

        return sprintf('%s-%d', $baseNumber, $maxSequence + 1);
    }
}

