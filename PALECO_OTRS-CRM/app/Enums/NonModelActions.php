<?php

namespace App\Enums;

/**
 * Tracks non-CRUD system events, specifically authentication milestones for audit logging.
 */
enum NonModelActions: string
{
    case LOGIN_SUCCESS = 'login_success';
    case LOGIN_FAILED = 'login_failed';
    case LOGIN_ACCOUNT_DEACTIVATED = 'login_pass_account_deactivated';

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
}
