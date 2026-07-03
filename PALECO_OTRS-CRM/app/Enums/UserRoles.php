<?php

namespace App\Enums;

enum UserRoles: string
{
    case ADMIN = 'admin';
    case CWD = 'cwd_officer';
    case FOREMAN = 'foreman';
    case FIELD_PERSONNEL = 'field_personnel';

    public function label(): string
    {
        return match($this) {
            self::ADMIN => 'Admin',
            self::CWD => 'CWD Officer',
            self::FOREMAN => 'Foreman',
            self::FIELD_PERSONNEL => 'Field Personnel',
        };
    }
}
