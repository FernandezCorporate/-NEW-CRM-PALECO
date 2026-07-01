<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\Auth\LoginRequest;
use App\Enums\UserRoles;
use Illuminate\Http\Request; 
use App\Enums\NonModelActions;
use Illuminate\Support\Facades\RateLimiter;
use App\Models\User;
use App\Events\LoginEvents;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request)
    {
        $username = strtolower((string)request()->input('username'));
        $user = User::query()->where('username', $username)->first();

        if ($user && $user->locked_until) {
            if ($user->locked_until > now()) {

                $minutesLeft = max(1, ceil(now()->diffInMinutes($user->locked_until)));

                return back()
                    ->withErrors(['error' => "This account is temporarily locked due to multiple failed attempts. 
                                Please wait {$minutesLeft} minute(s) or contact system administrator."])
                    ->onlyInput('username');
            }

            $user->updateQuietly(['locked_until' => null]);
        }

        $rateLimitKey = 'login:' . sha1($username . '|' . request()->ip());
        if (RateLimiter::tooManyAttempts($rateLimitKey, 4)){            
            if ($user) {
                if (!$user->locked_until) {
                $user->updateQuietly([
                    'locked_until' => now()->addMinutes(15)
                ]);

                $minutesLeft = max(1, ceil(now()->diffInMinutes($user->locked_until)));

                return back()
                    ->withErrors(['error' => "This account is temporarily locked due to multiple failed attempts. 
                                Please wait {$minutesLeft} minute(s) or contact system administrator."])
                    ->onlyInput('username');
                }
            }
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
            $userRole = $loggedUser->role;

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

            LoginEvents::dispatch(NonModelActions::LOGIN_SUCCESS, $loggedUser);
            $landingRoute = match($userRole) {
                UserRoles::ADMIN => route('admin.dashboard'),
                UserRoles::CWD => route('cwd.dashboard'),
                default => abort(403)
            };

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

        return redirect('/login');
    }
}
