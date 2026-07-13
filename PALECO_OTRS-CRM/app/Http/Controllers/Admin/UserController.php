<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AccountRole;
use App\Models\TeamRole; 
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

        $roles = AccountRole::orderBy('role_name')->get();

        $rawCounts = User::query()
                    ->where('is_active', true)
                    ->select('role_id')
                    ->selectRaw('count(*) as total') 
                    ->groupBy('role_id')
                    ->get()
                    ->keyBy('role_id');

        $activeCounts = (object) [
            'admin' => $rawCounts->get($roles->where('slug_identifier', 'admin')->first()?->id)?->total ?? 0,
            'cwd'   => $rawCounts->get($roles->where('slug_identifier', 'cwd_officer')->first()?->id)?->total ?? 0,
            'foreman' => $rawCounts->get($roles->where('slug_identifier', 'foreman')->first()?->id)?->total ?? 0,
            'field_personnel' => $rawCounts->get($roles->where('slug_identifier', 'field_personnel')->first()?->id)?->total ?? 0,
        ];

        $users = User::query()->with('role');

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

    public function show(User $user)
    {
        Gate::authorize('view', $user);

        // Load only the direct department for the user profile header
        $user->load('department');

        // Extract the teams into a separate paginated query
        $assignedTeams = $user->teams()
            ->with('department')
            ->withPivot('team_role_id', 'created_at')
            ->paginate(5);
            
        // Fetch the role dictionary
        $teamRoles = TeamRole::pluck('role_name', 'id');

        // Transform the paginated items to inject the string name directly onto each team
        $assignedTeams->getCollection()->transform(function ($team) use ($teamRoles) {
            $team->assigned_role_name = $teamRoles[$team->pivot->team_role_id] ?? 'Unknown Role';
            return $team;
        });

        // We no longer need to pass $teamRoles to the view!
        return view('admin.pages.userDetails', compact('user', 'assignedTeams'));
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

        $validatedData = $request->validated();

        $role = AccountRole::find($validatedData['role_id']);
        if ($role) {            
            if ($role->slug_identifier === 'field_personnel') {
                $validatedData['department_id'] = null;
            }
        }

        User::create($validatedData);

        return redirect()->route('admin.users')->with('success', 'User created successfully.');
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        Gate::authorize('update', $user);

        $validatedData = $request->validated();

        $role = AccountRole::find($validatedData['role_id']);
        if ($role) {
            if ($role->slug_identifier === 'field_personnel') {
                $validatedData['department_id'] = null;
            }
        }

        $user->fill($validatedData);

        if ($user->isClean()) {
            return redirect()->route('admin.users')->with('info', 'No changes were made to the user.');
        }

        DB::transaction(function () use ($user) {
            $user->save(); 
        });

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