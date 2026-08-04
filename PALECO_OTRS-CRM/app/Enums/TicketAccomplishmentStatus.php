<?php

namespace App\Enums;

enum TicketAccomplishmentStatus: string
{
    case PENDING = 'pending';   // The accomplishment is awaiting Foreman review.
    case APPROVED = 'approved'; // The accomplishment has been verified and accepted.
    case REJECTED = 'rejected'; // The accomplishment was rejected by the Foreman.

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Pending',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected'
        };
    }
}
