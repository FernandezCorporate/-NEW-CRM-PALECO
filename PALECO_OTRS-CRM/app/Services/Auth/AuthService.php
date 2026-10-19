<?php

namespace App\Services\Auth;

use App\Actions\Auth\AuthenticateWebUser;
use App\Actions\Auth\TerminateWebSession;
use Illuminate\Http\Request;

/**
 * Service encapsulating the core web authentication facade.
 * Delegates authentication logic to AuthenticateWebUser Action
 * and session invalidation to TerminateWebSession Action.
 */
class AuthService
{
    public function __construct(
        protected AuthenticateWebUser $authenticateWebUserAction,
        protected TerminateWebSession $terminateWebSessionAction
    ) {}

    // --- AUTHENTICATION & SESSION METHODS ---

    /**
     * Orchestrates the entire login attempt process.
     */
    public function processLogin(array $credentials, string $ip, string $expectedRole, Request $request): array
    {
        return $this->authenticateWebUserAction->execute($credentials, $ip, $expectedRole, $request);
    }

    /**
     * Safely flushes and invalidates the user's web session upon logout or forced termination.
     */
    public function terminateSession(Request $request): void
    {
        $this->terminateWebSessionAction->execute($request);
    }
}
