<?php

namespace App\Enums;

/**
 * Defines the verification review states of a ticket accomplishment report.
 */
enum TicketAccomplishmentStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    /**
     * Returns the human-readable text presentation of the accomplishment status.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
        };
    }
}
