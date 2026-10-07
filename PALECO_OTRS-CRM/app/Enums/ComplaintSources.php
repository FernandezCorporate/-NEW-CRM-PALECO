<?php

namespace App\Enums;

/**
 * Defines acceptable inputs for the complaint_source field in the tickets table.
 */
enum ComplaintSources: string
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
}
