<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\Auth\LoginRequest;
use App\Enums\UserRoles;
use Illuminate\Http\Request; 
class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request)
    {
        $credentials = $request->validated();

        if (Auth::attempt($credentials)){

            $loggedUser = Auth::user();

            $accountStatus = $loggedUser->is_active;
            $userRole = $loggedUser->role;

            if(!$accountStatus){
                Auth::logout();

                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()
                    ->withErrors(['error' => 'User account is deactivated. Please contact system administrator.'])
                    ->onlyInput('username');
            }

            $request->session()->regenerate();

            $landingRoute = match($userRole) {
                UserRoles::ADMIN => route('admin.dashboard'),
                UserRoles::CWD => route('cwd.dashboard'),
                default => abort(403)
            };

            return redirect()->intended($landingRoute);
        }

        return back()->withErrors([
            'error' => 'The provided credentials do not match our records.',
        ])->onlyInput('username');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
