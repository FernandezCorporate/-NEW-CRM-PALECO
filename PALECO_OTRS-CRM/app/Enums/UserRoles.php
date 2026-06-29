<?php

namespace App\Enums;

enum UserRoles: string
{
    case ADMIN = 'admin';
    case CWD = 'cwd_officer';
    case FOREMAN = 'foreman';
    case FIELD_PERSONNEL = 'field_personnel';
}
