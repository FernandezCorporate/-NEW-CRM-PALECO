<?php

namespace App\Enums;

use App\Contracts\HasVisualPresentation;

/**
 * Defines the possible review states of a cross-department ticket endorsement.
 */
enum EndorsementStatus: string implements HasVisualPresentation
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

    /**
     * Returns the Tailwind CSS classes for the status badge.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::PENDING => 'bg-amber-50 text-amber-700 border-amber-200',
            self::APPROVED => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            self::REJECTED => 'bg-rose-50 text-rose-700 border-rose-200',
        };
    }

    /**
     * Returns the hexadecimal color code for charts and graphics.
     */
    public function colorHex(): string
    {
        return match ($this) {
            self::PENDING => '#d97706',
            self::APPROVED => '#059669',
            self::REJECTED => '#e11d48',
        };
    }

    /**
     * Returns the Tailwind CSS background class for dots, markers, and chart bars.
     */
    public function chartClass(): string
    {
        return match ($this) {
            self::PENDING => 'bg-amber-500',
            self::APPROVED => 'bg-emerald-500',
            self::REJECTED => 'bg-rose-500',
        };
    }

    /**
     * Returns the Tailwind CSS background class for status dot markers.
     */
    public function dotClass(): string
    {
        return $this->chartClass();
    }
}
