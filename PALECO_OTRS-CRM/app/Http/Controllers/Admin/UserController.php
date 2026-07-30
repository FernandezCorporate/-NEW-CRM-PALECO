<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

use App\Http\Requests\Admin\User\StoreUserRequest;
use App\Http\Requests\Admin\User\UpdateUserRequest;

use App\Services\Admin\UserService;

use App\Models\AccountRole;
use App\Models\Department;
use App\Models\User;

/*
 * Manages the lifecycle and web interfaces for system User Accounts.
 * Handles user onboarding, profile updates, and active state toggling.
 */
class UserController extends Controller
{
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

        if (!$result['success']) {
            return redirect()->back()->with('error', $result['message'])->withInput();
        }

        if (!$result['changed']) {
            return redirect()->route('admin.users')->with('info', 'No changes were made to the user.');
        }

        return redirect()->route('admin.users')->with('success', 'User updated successfully.');
    }
    
    // --- DESTRUCTIVE & STATE METHODS ---

    /*
     * Renders the confirmation prompt for deactivating a user account.
     */
    public function deactivateConfirm(User $user)
    {
        Gate::authorize('deactivateConfirm', $user);
        return view('admin.prompts.userDeactivateConfirm', ['userAccount' => $user]);
    }

    /*
     * Executes the deactivation protocol to suspend access for the specified user.
     */
    public function deactivate(User $user)
    {
        Gate::authorize('deactivate', $user);
        $this->userService->toggleUserStatus($user, false);
        return redirect()->route('admin.users')->with('success', 'Account deactivated successfully.');
    }

    /*
     * Renders the confirmation prompt for reactivating a suspended user account.
     */
    public function reactivateConfirm(User $user)
    {
        Gate::authorize('reactivateConfirm', $user);
        return view('admin.prompts.userReactivateConfirm', ['userAccount' => $user]);
    }

    /*
     * Executes the reactivation protocol to restore access for the specified user.
     */
    public function reactivate(User $user)
    {
        Gate::authorize('reactivate', $user);
        $this->userService->toggleUserStatus($user, true);
        return redirect()->route('admin.users')->with('success', 'Account reactivated successfully.');
    }
}