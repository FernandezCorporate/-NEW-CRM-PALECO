<?php

namespace App\Enums;

/*
 * Manages the complete lifecycle stages of service tickets.
 * Maps operational states from creation to final supervisor verification.
 */
enum TicketStatus: string
{
    case OPEN = 'open';               // CWD created it, department assigned.
    case ASSIGNED = 'assigned';       // Foreman assigned it to a specific field team.
    case IN_PROGRESS = 'in_progress'; // Field personnel accepted it and are working.
    case PENDING_ESCALATION = 'pending_escalation'; // Field personnel requested escalation to CWD.
    case ESCALATED = 'escalated';     // CWD has escalated ticket to another department.
    case RESOLVED = 'resolved';       // Field personnel submitted proof of completion.
    case CLOSED = 'closed';           // Foreman verified the proof and closed the ticket.

    /*
     * Returns the human-readable text presentation of the status.
     * Used on administrative data tables and worker mobile interfaces.
     */
    public function label(): string
    {
        return match($this) {
            self::OPEN => 'Open',
            self::ASSIGNED => 'Assigned',
            self::IN_PROGRESS => 'In Progress',
            self::PENDING_ESCALATION => 'Pending Escalation',
            self::ESCALATED => 'Escalated',
            self::RESOLVED => 'Resolved',
            self::CLOSED => 'Closed'
        };
    }
}
