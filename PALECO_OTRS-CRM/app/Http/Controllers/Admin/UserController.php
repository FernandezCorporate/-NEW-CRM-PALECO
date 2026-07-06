<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Enums\UserRoles;
use App\Http\Requests\Admin\User\StoreUserRequest;
use App\Http\Requests\Admin\User\UpdateUserRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use App\Models\Department;

class UserController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', User::class);

        $roles = UserRoles::cases();

        $rawCounts = User::query()->where('is_active', true)
            ->pluck('role')
            ->countBy();

        $activeCounts = (object) [
            'admin' => $rawCounts->get(UserRoles::ADMIN->value, 0),
            'cwd'   => $rawCounts->get(UserRoles::CWD->value, 0),
            'foreman' => $rawCounts->get(UserRoles::FOREMAN->value, 0),
            'field_personnel' => $rawCounts->get(UserRoles::FIELD_PERSONNEL->value, 0),
        ];

        $users = User::query();

        if ($request->filled('search')) {
            $users = $users->search($request->search);
        }

        if ($request->filled('filter') && $request->filter !== 'all') {
            $users = $users->filter($request->filter);
        }

        if ($request->filled('sort')) {
            $users = $users->sort($request->sort);
        } else {
            $users = $users->latest();
        }

        $users = $users->paginate(10)->withQueryString();

        return view('admin.pages.userManagement', compact('users', 'roles', 'activeCounts'));
    }

    public function userForm(?User $user = null)
    {
        Gate::authorize('userForm', User::class);

        $depts = Department::whereNull('deleted_at')->orderBy('dept_name')->pluck('dept_name', 'id');
        $roles = UserRoles::cases();

        return view('admin.forms.userForm', compact('user', 'depts', 'roles'));
    } 

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request)
    {
        Gate::authorize('create', User::class);

        $validatedData = $request->validated();

        User::create($validatedData);

        return redirect()->route('admin.users')->with('success', 'User created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        //
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        Gate::authorize('update', $user);

        $user->fill($request->validated());

        if ($user->isClean()) {
            return redirect()->route('admin.users')->with('info', 'No changes were made to the user.');
        }

        $user->save();

        return redirect()->route('admin.users')->with('success', 'Users updated successfully.');
    }

    public function deactivateConfirm(User $user)
    {
        Gate::authorize('deactivateConfirm', $user);

        return view('admin.prompts.userDeactivateConfirm', ['userAccount' => $user]);
    }

    public function deactivate(User $user)
    {
        Gate::authorize('deactivate', $user);

        $user->is_active = false;
        $user->save();

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

        $user->is_active = true;
        $user->save();

        return redirect()->route('admin.users')->with('success', 'Account reactivated successfully.');
    }
}
