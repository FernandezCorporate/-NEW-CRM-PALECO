<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\AuthService;
use Illuminate\Http\Request; 

class AuthController extends Controller
{
    public function showRoleSelection()
    {
        return view('auth.roleSelection');
    }

    public function showLoginForm($role)
    {
        $allowedPortals = ['admin', 'cwd_officer'];

        if (!in_array($role, $allowedPortals)) {
            return redirect()->route('portal')->withErrors(['error' => 'Invalid portal selection.']);
        }

        return view('auth.login', compact('role'));
    }

    // Notice we inject the new AuthService here!
    public function login(LoginRequest $request, $role, AuthService $authService)
    {
        // 1. Hand the HTTP request data over to the Service layer
        $result = $authService->processLogin(
            $request->validated(), 
            $request->ip(), 
            $role, 
            $request
        );

        // 2. HTTP Layer Response: If login failed, send them back with the error
        if (!$result['success']) {
            return back()->withErrors(['error' => $result['message']])->onlyInput('username');
        }

        // 3. HTTP Layer Response: If successful, redirect them to the safe URL
        return redirect($result['redirect_url']);
    }

    public function logout(Request $request, AuthService $authService)
    {
        $authService->terminateSession($request);
        return redirect('/portal');
    }
}