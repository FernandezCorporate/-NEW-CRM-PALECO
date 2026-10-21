<?php

namespace App\Actions\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Single-Action: Safely flushes and invalidates the user's web session upon logout or forced termination.
 */
class TerminateWebSession
{
    public function handle(Request $request): void
    {
        $this->execute($request);
    }

    public function execute(Request $request): void
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
