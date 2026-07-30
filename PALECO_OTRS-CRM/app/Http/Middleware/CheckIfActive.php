<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckIfActive
{
    public function handle(Request $request, Closure $next)
    {
        // 1. Check if the user is logged in
        // 2. Check if their account was flipped to inactive
        if (Auth::check() && !Auth::user()->is_active) {
            
            // Instantly destroy their web session
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // Kick them back to the login screen with a message
            return redirect()->route('login')->with('error', 'Your account has been deactivated. Please contact the administrator.');
        }

        return $next($request);
    }
}