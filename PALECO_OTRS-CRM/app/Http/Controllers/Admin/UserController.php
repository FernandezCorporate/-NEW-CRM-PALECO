<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AccountRole;
use App\Models\Department;
use App\Http\Requests\Admin\User\StoreUserRequest;
use App\Http\Requests\Admin\User\UpdateUserRequest;
use App\Services\Admin\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function __construct(protected UserService $userService) {}

    public function index(Request $request)
    {
        Gate::authorize('viewAny', User::class);

        $dashboardData = $this->userService->getDashboardUsers($request->all());

        return view('admin.pages.userManagement', $dashboardData);
    }

    public function show(User $user)
    {
        Gate::authorize('view', $user);

        $details = $this->userService->getUserDetails($user);

        return view('admin.pages.userDetails', array_merge(['user' => $user], $details));
    }

    public function userForm(?User $user = null)
    {
        Gate::authorize('userForm', $user ?? User::class);

        $depts = Department::orderBy('dept_name')->pluck('dept_name', 'id');
        $roles = AccountRole::orderBy('role_name')->get();

        return view('admin.forms.userForm', compact('user', 'depts', 'roles'));
    }

    public function store(StoreUserRequest $request)
    {
        Gate::authorize('create', User::class);

        $this->userService->processAndSaveUser($request->validated());

        return redirect()->route('admin.users')->with('success', 'User created successfully.');
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        Gate::authorize('update', $user);

        $wasUpdated = $this->userService->processAndSaveUser($request->validated(), $user);

        if (!$wasUpdated) {
            return redirect()->route('admin.users')->with('info', 'No changes were made to the user.');
        }

        return redirect()->route('admin.users')->with('success', 'User updated successfully.');
    }
    
    public function deactivateConfirm(User $user)
    {
        Gate::authorize('deactivateConfirm', $user);
        return view('admin.prompts.userDeactivateConfirm', ['userAccount' => $user]);
    }

    public function deactivate(User $user)
    {
        Gate::authorize('deactivate', $user);
        $this->userService->toggleUserStatus($user, false);
        return redirect()->route('admin.users')->with('success', 'Account deactivated successfully.');
    }

    public function reactivateConfirm(User $user)
    {
        Gate::authorize('reactivateConfirm', $user);
        return view('admin.prompts.userReactivateConfirm', ['userAccount' => $user]);
    }

    public function reactivate(User $user)
    {
        Gate::authorize('reactivate', $user);
        $this->userService->toggleUserStatus($user, true);
        return redirect()->route('admin.users')->with('success', 'Account reactivated successfully.');
    }
}