<?php

namespace App\Enums;

/**
 * Manages the complete lifecycle stages of service tickets.
 * Maps operational states from creation to final supervisor verification.
 */
enum TicketStatus: string
{
    case OPEN = 'open';
    case ASSIGNED = 'assigned';
    case IN_PROGRESS = 'in_progress';
    case PENDING_ENDORSEMENT = 'pending_endorsement';
    case ENDORSED = 'endorsed';
    case RESOLVED = 'resolved';
    case CLOSED = 'closed';

    /**
     * Returns the human-readable text presentation of the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Open',
            self::ASSIGNED => 'Assigned',
            self::IN_PROGRESS => 'In Progress',
            self::PENDING_ENDORSEMENT => 'Pending Endorsement',
            self::ENDORSED => 'Endorsed',
            self::RESOLVED => 'Resolved',
            self::CLOSED => 'Closed',
        };
    }
}
