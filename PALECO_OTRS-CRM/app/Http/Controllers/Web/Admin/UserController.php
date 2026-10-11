<?php

namespace App\Http\Controllers\Web\Admin;

use App\Concerns\ResolvesSmartRedirects;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\AccountRole;
use App\Models\Department;
use App\Models\User;
use App\Services\Users\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/*
 * Manages the lifecycle and web interfaces for system User Accounts.
 * Handles user onboarding, profile updates, and active state toggling.
 */
class UserController extends Controller
{
    use ResolvesSmartRedirects;

    /*
     * Injects the UserService to handle complex user processing and database transactions.
     */
    public function __construct(protected UserService $userService) {}

    // --- VIEW METHODS ---

    /*
     * Retrieves and renders the paginated list of user accounts for the management dashboard.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', User::class);

        $dashboardData = $this->userService->getDashboardUsers($request->all());

        session()->put('user_list_url', $request->fullUrl());

        return view('admin.pages.userManagement', $dashboardData);
    }

    /*
     * Retrieves and renders the detailed profile of a specific user.
     */
    public function show(User $user)
    {
        Gate::authorize('view', $user);

        $details = $this->userService->getUserDetails($user);

        return view('admin.pages.userDetails', array_merge(['user' => $user], $details));
    }

    // --- FORM METHODS ---

    /*
     * Renders the unified form used for both creating and updating user accounts, alongside required dropdown data.
     */
    public function userForm(?User $user = null)
    {
        Gate::authorize('userForm', $user ?? User::class);

        $depts = Department::orderBy('dept_name')->pluck('dept_name', 'id');
        $roles = AccountRole::orderBy('role_name')->get();

        return view('admin.forms.userForm', compact('user', 'depts', 'roles'));
    }

    // --- MUTATING METHODS ---

    /*
     * Processes validated request data to configure and store a new user account.
     */
    public function store(StoreUserRequest $request)
    {
        Gate::authorize('create', User::class);

        $this->userService->processAndSaveUser($request->validated());

        return redirect()->route('admin.users')->with('success', 'User created successfully.');
    }

    /*
     * Processes validated request data to commit updates to an existing user account profile.
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        Gate::authorize('update', $user);

        $result = $this->userService->processAndSaveUser($request->validated(), $user);

        if (! $result['success']) {
            return redirect()->back()->with('error', $result['message'])->withInput();
        }

        $redirectRoute = $this->resolveSmartRedirect($request, 'admin.users', [
            'details' => function (Request $req) use ($user) {
                $teamId = $req->input('team', $req->query('team'));
                $deptId = $req->input('dept', $req->query('dept'));
                return route('admin.users.show', array_filter([
                    'user' => $user,
                    'source' => $teamId ? 'team' : ($deptId ? 'department' : null),
                    'team' => $teamId,
                    'dept' => $deptId,
                ]));
            },
            'team' => function (Request $req) use ($user) {
                $teamId = $req->input('team', $req->query('team'));
                return $teamId ? route('admin.teams.show', ['team' => $teamId]) : route('admin.users.show', ['user' => $user]);
            },
            'department' => function (Request $req) use ($user) {
                $deptId = $req->input('dept', $req->query('dept', $user->department_id));
                return $deptId ? route('admin.departments.show', ['dept' => $deptId]) : route('admin.users.show', ['user' => $user]);
            },
        ]);

        if (! $result['changed']) {
            return redirect($redirectRoute)->with('info', 'No changes were made to the user.');
        }

        return redirect($redirectRoute)->with('success', 'User updated successfully.');
    }

    // --- DESTRUCTIVE & STATE METHODS ---

    public function deactivateConfirm(User $user)
    {
        if (! $user->is_active) {
            return redirect()->route('admin.users')->with('info', 'This account is already deactivated.');
        }

        Gate::authorize('deactivateConfirm', $user);

        return view('admin.prompts.userDeactivateConfirm', ['userAccount' => $user]);
    }

    public function deactivate(Request $request, User $user)
    {
        if (! $user->is_active) {
            return redirect()->route('admin.users')->with('info', 'This account has already been deactivated by another administrator.');
        }

        Gate::authorize('deactivate', $user);
        $this->userService->toggleUserStatus($user, false);

        $redirectRoute = $this->resolveSmartRedirect($request, 'admin.users', [
            'details' => function (Request $req) use ($user) {
                $teamId = $req->input('team', $req->query('team'));
                $deptId = $req->input('dept', $req->query('dept'));
                return route('admin.users.show', array_filter([
                    'user' => $user,
                    'source' => $teamId ? 'team' : ($deptId ? 'department' : null),
                    'team' => $teamId,
                    'dept' => $deptId,
                ]));
            },
            'team' => function (Request $req) use ($user) {
                $teamId = $req->input('team', $req->query('team'));
                return $teamId ? route('admin.teams.show', ['team' => $teamId]) : route('admin.users.show', ['user' => $user]);
            },
            'department' => function (Request $req) use ($user) {
                $deptId = $req->input('dept', $req->query('dept', $user->department_id));
                return $deptId ? route('admin.departments.show', ['dept' => $deptId]) : route('admin.users.show', ['user' => $user]);
            },
        ]);

        return redirect($redirectRoute)->with('success', 'Account deactivated successfully.');
    }

    public function reactivateConfirm(User $user)
    {
        if ($user->is_active) {
            return redirect()->route('admin.users')->with('info', 'This account is already active.');
        }

        Gate::authorize('reactivateConfirm', $user);

        return view('admin.prompts.userReactivateConfirm', ['userAccount' => $user]);
    }

    public function reactivate(Request $request, User $user)
    {
        if ($user->is_active) {
            return redirect()->route('admin.users')->with('info', 'This account has already been reactivated by another administrator.');
        }

        Gate::authorize('reactivate', $user);
        $this->userService->toggleUserStatus($user, true);

        $redirectRoute = $this->resolveSmartRedirect($request, 'admin.users', [
            'details' => function (Request $req) use ($user) {
                $teamId = $req->input('team', $req->query('team'));
                $deptId = $req->input('dept', $req->query('dept'));
                return route('admin.users.show', array_filter([
                    'user' => $user,
                    'source' => $teamId ? 'team' : ($deptId ? 'department' : null),
                    'team' => $teamId,
                    'dept' => $deptId,
                ]));
            },
            'team' => function (Request $req) use ($user) {
                $teamId = $req->input('team', $req->query('team'));
                return $teamId ? route('admin.teams.show', ['team' => $teamId]) : route('admin.users.show', ['user' => $user]);
            },
            'department' => function (Request $req) use ($user) {
                $deptId = $req->input('dept', $req->query('dept', $user->department_id));
                return $deptId ? route('admin.departments.show', ['dept' => $deptId]) : route('admin.users.show', ['user' => $user]);
            },
        ]);

        return redirect($redirectRoute)->with('success', 'Account reactivated successfully.');
    }
}
