<?php

namespace App\Enums;

use App\Contracts\HasVisualPresentation;

/**
 * Defines acceptable inputs for the complaint_source field in the tickets table.
 */
enum ComplaintSources: string implements HasVisualPresentation
{
    case PHONE_CALL = 'phone_call';
    case WALK_IN = 'walk_in';
    case SMS = 'sms_message';
    case ONLINE = 'online_platforms';
    case EMAIL = 'email';

    /**
     * Returns a properly formatted string matching the enum instance.
     */
    public function label(): string
    {
        return match ($this) {
            self::PHONE_CALL => 'Phone call',
            self::WALK_IN => 'Walk-in',
            self::SMS => 'SMS or Text message',
            self::ONLINE => 'Online platforms (Facebook, messenger, etc.)',
            self::EMAIL => 'Email',
        };
    }

    /**
     * Returns the Tailwind CSS classes for the badge.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::PHONE_CALL => 'bg-blue-50 text-blue-700 border-blue-200',
            self::WALK_IN => 'bg-teal-50 text-teal-700 border-teal-200',
            self::SMS => 'bg-cyan-50 text-cyan-700 border-cyan-200',
            self::ONLINE => 'bg-violet-50 text-violet-700 border-violet-200',
            self::EMAIL => 'bg-indigo-50 text-indigo-700 border-indigo-200',
        };
    }

    /**
     * Returns the hexadecimal color code for charts and graphics.
     */
    public function colorHex(): string
    {
        return match ($this) {
            self::PHONE_CALL => '#2563eb',
            self::WALK_IN => '#0d9488',
            self::SMS => '#0891b2',
            self::ONLINE => '#7c3aed',
            self::EMAIL => '#4f46e5',
        };
    }

    /**
     * Returns the Tailwind CSS background class for dots, markers, and chart bars.
     */
    public function chartClass(): string
    {
        return match ($this) {
            self::PHONE_CALL => 'bg-blue-500',
            self::WALK_IN => 'bg-teal-500',
            self::SMS => 'bg-cyan-500',
            self::ONLINE => 'bg-violet-500',
            self::EMAIL => 'bg-indigo-500',
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
