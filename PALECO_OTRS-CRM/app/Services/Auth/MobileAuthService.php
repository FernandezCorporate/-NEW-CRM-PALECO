<?php

namespace App\Services\Auth;

use App\Actions\Auth\AuthenticateMobileUser;

/**
 * Service encapsulating the mobile API authentication facade.
 * Delegates authentication logic to AuthenticateMobileUser Action.
 */
class MobileAuthService
{
    public function __construct(
        protected AuthenticateMobileUser $authenticateMobileUserAction
    ) {}

    /**
     * Orchestrates the mobile login attempt.
     */
    public function processLogin(array $credentials, string $ip): array
    {
        return $this->authenticateMobileUserAction->execute($credentials, $ip);
    }
}
