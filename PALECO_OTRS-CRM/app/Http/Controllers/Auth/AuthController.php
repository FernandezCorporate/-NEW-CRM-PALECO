<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request; 
use App\Enums\NonModelActions;
use Illuminate\Support\Facades\RateLimiter;
use App\Models\User;
use App\Events\LoginEvents;

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

    public function login(LoginRequest $request, $role)
    {
        $username = strtolower((string)$request->input('username'));
        $user = User::query()->where('username', $username)->first();

        if ($user && $user->locked_until) {
            if ($user->locked_until > now()) {
                LoginEvents::dispatch(NonModelActions::LOGIN_FAILED, $user);
                $minutesLeft = max(1, ceil(now()->diffInMinutes($user->locked_until)));

                return back()
                    ->withErrors(['error' => "This account is temporarily locked due to multiple failed attempts. Please wait {$minutesLeft} minute(s)."])
                    ->onlyInput('username');
            }
            $user->updateQuietly(['locked_until' => null]);
        }

        $rateLimitKey = 'login:' . sha1($username . '|' . request()->ip());
        
        if (RateLimiter::tooManyAttempts($rateLimitKey, 4)){            
            if ($user) {
                if (!$user->locked_until) {
                    $user->updateQuietly(['locked_until' => now()->addMinutes(15)]);
                    LoginEvents::dispatch(NonModelActions::LOGIN_FAILED, $user);
                    $minutesLeft = max(1, ceil(now()->diffInMinutes($user->locked_until)));

                    return back()
                        ->withErrors(['error' => "This account is temporarily locked due to multiple failed attempts. Please wait {$minutesLeft} minute(s)."])
                        ->onlyInput('username');
                }
            }
            
            LoginEvents::dispatch(NonModelActions::LOGIN_FAILED, $user);
            $availableAgain = RateLimiter::availableIn($rateLimitKey);
            return back()
                ->withErrors(['error' => "Too many attempts. Try again after {$availableAgain} seconds."])
                ->onlyInput('username');
        }

        $credentials = $request->validated();

        if (Auth::attempt($credentials)){
            RateLimiter::clear($rateLimitKey);

            $loggedUser = Auth::user();
            $accountStatus = $loggedUser->is_active;
            $userRoleSlug = $loggedUser->role->slug_identifier;

            if ($userRoleSlug !== $role) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                LoginEvents::dispatch(NonModelActions::LOGIN_FAILED, $loggedUser);

                return back()
                    ->withErrors(['error' => 'Access Denied: Your account credentials are valid, but you do not have permission to access this specific portal.'])
                    ->onlyInput('username');
            }

            if(!$accountStatus){
                LoginEvents::dispatch(NonModelActions::LOGIN_ACCOUNT_DEACTIVATED, $loggedUser);
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()
                    ->withErrors(['error' => 'User account is deactivated. Please contact system administrator.'])
                    ->onlyInput('username');
            }

            $request->session()->regenerate();
            $loggedUser->updateQuietly(['last_login' => now()]);
            LoginEvents::dispatch(NonModelActions::LOGIN_SUCCESS, $loggedUser);
            
            $landingRoute = match($userRoleSlug) {
                'admin' => route('admin.dashboard'),
                'cwd_officer' => route('cwd.dashboard'),
                default => null 
            };

            if (!$landingRoute) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()
                    ->withErrors(['error' => 'Access Denied: Your account role does not have web portal privileges.'])
                    ->onlyInput('username');
            }

            return redirect()->intended($landingRoute);
        }
        
        RateLimiter::hit($rateLimitKey, 60);
        LoginEvents::dispatch(NonModelActions::LOGIN_FAILED, null);

        return back()->withErrors([
            'error' => 'The provided credentials do not match our records.',
        ])->onlyInput('username');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/portal');
    }
}