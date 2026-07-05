<?php

namespace App\Enums;

enum TeamMemberRoles: string
{
    case LEADER = 'leader';
    case MEMBER = 'member';
    case BACKUP = 'backup';

    public function label(): string
    {
        return match($this) {
            self::LEADER => 'Team Leader',
            self::MEMBER => 'Team Member',
            self::BACKUP => 'Backup Member',
        };
    }
}
