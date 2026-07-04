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

        $depts = Department::query()->whereNull('deleted_at')->get();
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

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        //
    }
}
