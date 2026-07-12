<?php

namespace App\Enums;

enum TicketStatus: string
{
    case OPEN = 'open';               // CWD created it, department assigned.
    case ASSIGNED = 'assigned';       // Foreman assigned it to a specific field team.
    case IN_PROGRESS = 'in_progress'; // Field personnel accepted it and are working.
    case RESOLVED = 'resolved';       // Field personnel submitted proof of completion.
    case CLOSED = 'closed';           // Foreman verified the proof and closed the ticket.

    public function label(): string
    {
        return match($this) {
            self::OPEN => 'Open',
            self::ASSIGNED => 'Assigned',
            self::IN_PROGRESS => 'In Progress',
            self::RESOLVED => 'Resolved',
            self::CLOSED => 'Closed'
        };
    }
}