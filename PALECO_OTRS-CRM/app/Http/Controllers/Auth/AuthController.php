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
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request)
    {
        $username = strtolower((string)$request->input('username'));
        $user = User::query()->where('username', $username)->first();

        // Check if the account is already locked from a previous session
        if ($user && $user->locked_until) {
            if ($user->locked_until > now()) {
                
                // Log attempts made while the account is already in a lockout state
                LoginEvents::dispatch(NonModelActions::LOGIN_FAILED, $user);

                $minutesLeft = max(1, ceil(now()->diffInMinutes($user->locked_until)));

                return back()
                    ->withErrors(['error' => "This account is temporarily locked due to multiple failed attempts. 
                                Please wait {$minutesLeft} minute(s) or contact system administrator."])
                    ->onlyInput('username');
            }

            // Lockout expired, remove the lock
            $user->updateQuietly(['locked_until' => null]);
        }

        $rateLimitKey = 'login:' . sha1($username . '|' . request()->ip());
        
        // Handle 5th failed attempt and apply rate limiting
        if (RateLimiter::tooManyAttempts($rateLimitKey, 4)){            
            if ($user) {
                if (!$user->locked_until) {
                    $user->updateQuietly([
                        'locked_until' => now()->addMinutes(15)
                    ]);

                    // Log the exact attempt that triggered the 15-minute lockout
                    LoginEvents::dispatch(NonModelActions::LOGIN_FAILED, $user);

                    $minutesLeft = max(1, ceil(now()->diffInMinutes($user->locked_until)));

                    return back()
                        ->withErrors(['error' => "This account is temporarily locked due to multiple failed attempts. 
                                    Please wait {$minutesLeft} minute(s) or contact system administrator."])
                        ->onlyInput('username');
                }
            }
            
            // Log rate-limited attempts (e.g., from same IP but non-existent user)
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

            // Synchronously update the last login time instantly
            $loggedUser->updateQuietly(['last_login' => now()]);

            // Dispatch background logging event
            LoginEvents::dispatch(NonModelActions::LOGIN_SUCCESS, $loggedUser);
            
            $landingRoute = match($userRoleSlug) {
                'admin' => route('admin.dashboard'),
                'cwd_officer' => route('cwd.dashboard'),
                default => null // Catch roles without web access
            };

            // Safely reject roles that are not meant to use the web dashboard
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
        
        // Handle attempts 1 through 4
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

        return redirect('/login');
    }
}