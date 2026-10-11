<?php

namespace App\Enums;

use App\Contracts\HasVisualPresentation;

/**
 * Tracks non-CRUD system events, specifically authentication milestones for audit logging.
 */
enum NonModelActions: string implements HasVisualPresentation
{
    case LOGIN_SUCCESS = 'login_success';
    case LOGIN_FAILED = 'login_failed';
    case LOGIN_ACCOUNT_DEACTIVATED = 'login_pass_account_deactivated';

    /**
     * Returns the human-readable text presentation of the action.
     */
    public function label(): string
    {
        return match ($this) {
            self::LOGIN_SUCCESS => 'Successful Login',
            self::LOGIN_FAILED => 'Failed Login',
            self::LOGIN_ACCOUNT_DEACTIVATED => 'Account Deactivated',
        };
    }

    /**
     * Returns a detailed description of the authentication outcome.
     */
    public function description(): string
    {
        return match ($this) {
            self::LOGIN_SUCCESS => 'Login attempt was successful.',
            self::LOGIN_FAILED => 'Login attempt failed.',
            self::LOGIN_ACCOUNT_DEACTIVATED => 'Login credentials matched but account is deactivated',
        };
    }

    /**
     * Returns the custom event identifier for activity logging.
     */
    public function event(): string
    {
        return match ($this) {
            self::LOGIN_SUCCESS, self::LOGIN_FAILED, self::LOGIN_ACCOUNT_DEACTIVATED => 'login',
        };
    }

    /**
     * Returns the Tailwind CSS classes for the status badge.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::LOGIN_SUCCESS => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            self::LOGIN_FAILED => 'bg-rose-50 text-rose-700 border-rose-200',
            self::LOGIN_ACCOUNT_DEACTIVATED => 'bg-amber-50 text-amber-700 border-amber-200',
        };
    }

    /**
     * Returns the hexadecimal color code for charts and graphics.
     */
    public function colorHex(): string
    {
        return match ($this) {
            self::LOGIN_SUCCESS => '#059669',
            self::LOGIN_FAILED => '#e11d48',
            self::LOGIN_ACCOUNT_DEACTIVATED => '#d97706',
        };
    }

    /**
     * Returns the Tailwind CSS background class for dots, markers, and chart bars.
     */
    public function chartClass(): string
    {
        return match ($this) {
            self::LOGIN_SUCCESS => 'bg-emerald-500',
            self::LOGIN_FAILED => 'bg-rose-500',
            self::LOGIN_ACCOUNT_DEACTIVATED => 'bg-amber-500',
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
