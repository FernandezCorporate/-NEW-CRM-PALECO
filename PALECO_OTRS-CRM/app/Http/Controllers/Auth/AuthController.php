<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\Auth\LoginRequest;
use App\Enums\UserRoles;
use Illuminate\Http\Request; 
use App\Enums\NonModelActions;
use Spatie\Activitylog\Contracts\Activity;

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
                $this->auditAction(NonModelActions::LOGIN_ACCOUNT_DEACTIVATED);

                Auth::logout();

                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()
                    ->withErrors(['error' => 'User account is deactivated. Please contact system administrator.'])
                    ->onlyInput('username');
            }

            $request->session()->regenerate();

            $this->auditAction(NonModelActions::LOGIN_SUCCESS);

            $landingRoute = match($userRole) {
                UserRoles::ADMIN => route('admin.dashboard'),
                UserRoles::CWD => route('cwd.dashboard'),
                default => abort(403)
            };

            return redirect()->intended($landingRoute);
        }

        $this->auditAction(NonModelActions::LOGIN_FAILED);

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

public function auditAction(NonModelActions $action_category)
    {        
        activity()
            ->useLog($action_category->value)
            ->event($action_category->event())
            ->causedBy(Auth::user())
            ->withProperties([
                "ip_address" => request()->ip(),
                "user_agent" => request()->userAgent(),
                "username"   => Auth::user() ? Auth::user()->username : request()->username, 
                "full_name"  => Auth::user() ? ucwords(trim(Auth::user()->first_name . ' ' . Auth::user()->last_name)) : null,
                "role"       => Auth::user()?->role?->value,
                "email"      => Auth::user()?->email,
                "contact"    => Auth::user()?->contact
            ])
            ->log($action_category->description());
    }
}
