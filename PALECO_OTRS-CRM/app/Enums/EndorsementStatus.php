<?php

namespace App\Enums;

/**
 * Defines the possible review states of a cross-department ticket endorsement.
 */
enum EndorsementStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    /**
     * Returns the human-readable text presentation of the endorsement status.
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
