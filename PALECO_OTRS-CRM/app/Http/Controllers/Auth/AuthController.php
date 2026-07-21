<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request; 

use App\Http\Requests\Auth\LoginRequest;

use App\Services\Auth\AuthService;

/*
 * Manages the web-based authentication lifecycle.
 * Handles role-based portal routing, login processing, and secure session termination.
 */
class AuthController extends Controller
{
    // --- FORM METHODS ---

    /*
     * Renders the initial role selection interface for users accessing the portal.
     */
    public function showRoleSelection()
    {
        return view('auth.roleSelection');
    }

    /*
     * Validates the selected portal role and renders the corresponding login form.
     */
    public function showLoginForm($role)
    {
        $allowedPortals = ['admin', 'cwd_officer'];

        if (!in_array($role, $allowedPortals)) {
            return redirect()->route('portal')->withErrors(['error' => 'Invalid portal selection.']);
        }

        return view('auth.login', compact('role'));
    }

    // --- MUTATING METHODS ---

    /*
     * Processes the login attempt by handing validated credentials over to the AuthService.
     * Redirects to the appropriate dashboard on success or returns validation errors on failure.
     */
    public function login(LoginRequest $request, $role, AuthService $authService)
    {
        $result = $authService->processLogin(
            $request->validated(), 
            $request->ip(), 
            $role, 
            $request
        );

        if (!$result['success']) {
            return back()->withErrors(['error' => $result['message']])->onlyInput('username');
        }

        return redirect($result['redirect_url']);
    }

    /*
     * Safely terminates the user's active session and redirects them back to the portal.
     */
    public function logout(Request $request, AuthService $authService)
    {
        $authService->terminateSession($request);
        return redirect('/portal');
    }
}