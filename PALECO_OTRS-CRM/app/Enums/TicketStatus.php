<?php

namespace App\Enums;

use App\Contracts\HasVisualPresentation;

/**
 * Manages the complete lifecycle stages of service tickets.
 * Maps operational states from creation to final supervisor verification.
 */
enum TicketStatus: string implements HasVisualPresentation
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

    /**
     * Returns the Tailwind CSS classes for the status badge.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::OPEN => 'bg-sky-50 text-sky-700 border-sky-200',
            self::ASSIGNED => 'bg-purple-50 text-purple-700 border-purple-200',
            self::IN_PROGRESS => 'bg-amber-50 text-amber-700 border-amber-200',
            self::PENDING_ENDORSEMENT => 'bg-orange-50 text-orange-700 border-orange-200',
            self::ENDORSED => 'bg-indigo-50 text-indigo-700 border-indigo-200',
            self::RESOLVED => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            self::CLOSED => 'bg-slate-100 text-slate-700 border-slate-300',
        };
    }

    /**
     * Returns the hexadecimal color code for charts and graphics.
     */
    public function colorHex(): string
    {
        return match ($this) {
            self::OPEN => '#0284c7',
            self::ASSIGNED => '#9333ea',
            self::IN_PROGRESS => '#d97706',
            self::PENDING_ENDORSEMENT => '#ea580c',
            self::ENDORSED => '#4f46e5',
            self::RESOLVED => '#059669',
            self::CLOSED => '#64748b',
        };
    }

    /**
     * Returns the Tailwind CSS background class for dots, markers, and chart bars.
     */
    public function chartClass(): string
    {
        return match ($this) {
            self::OPEN => 'bg-sky-500',
            self::ASSIGNED => 'bg-purple-500',
            self::IN_PROGRESS => 'bg-amber-500',
            self::PENDING_ENDORSEMENT => 'bg-orange-500',
            self::ENDORSED => 'bg-indigo-500',
            self::RESOLVED => 'bg-emerald-500',
            self::CLOSED => 'bg-slate-500',
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
