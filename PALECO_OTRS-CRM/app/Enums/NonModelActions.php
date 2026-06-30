<?php

namespace App\Enums;

enum NonModelActions: string
{
    case LOGIN_SUCCESS = 'login_success';
    case LOGIN_FAILED = 'login_failed';
    case LOGIN_ACCOUNT_DEACTIVATED = 'login_pass_account_deactivated';

    public function description()
    {
        return match($this) {
            self::LOGIN_SUCCESS => 'Login attempt was successful.',
            self::LOGIN_FAILED => 'Login attempt failed.',
            self::LOGIN_ACCOUNT_DEACTIVATED => 'Login credentials matched but account is deactivated'
        };
    }

    public function event()
    {
        return match($this) {
            self::LOGIN_SUCCESS, self::LOGIN_FAILED, self::LOGIN_ACCOUNT_DEACTIVATED => 'login'
        };
    }
}
