<?php

namespace App\Enums;

/*
 * Tracks non-CRUD system events, specifically authentication milestones.
 * Standardizes activity log names for the Spatie Activitylog package.
 * Defines the valid values for the log_name field on the activity_log table.
 */
enum NonModelActions: string
{
    case LOGIN_SUCCESS = 'login_success';
    case LOGIN_FAILED = 'login_failed';
    case LOGIN_ACCOUNT_DEACTIVATED = 'login_pass_account_deactivated';

    /*
     * Returns a detailed description of the authentication outcome.
     * Used directly in populating the description field on the activity_log table.
     * Each description outcome is tied to exactly one NonModelActions instance.
     */
    public function description(): string
    {
        return match($this) {
            self::LOGIN_SUCCESS => 'Login attempt was successful.',
            self::LOGIN_FAILED => 'Login attempt failed.',
            self::LOGIN_ACCOUNT_DEACTIVATED => 'Login credentials matched but account is deactivated'
        };
    }

    /*
     * Defines a custom event called 'login'. 
     * Ensures all NonModelActions instance use this custom-made value for the event field on the activity_log table.
     */
    public function event(): string
    {
        return match($this) {
            self::LOGIN_SUCCESS, self::LOGIN_FAILED, self::LOGIN_ACCOUNT_DEACTIVATED => 'login'
        };
    }
}
