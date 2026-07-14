<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Models\AccountRole;
use App\Models\TeamRole;
use Illuminate\Support\Facades\DB;

class UserService
{
    public function getDashboardUsers(array $filters): array
    {
        $roles = AccountRole::orderBy('role_name')->get();

        $rawCounts = User::query()->where('is_active', true)
            ->select('role_id', DB::raw('count(*) as total'))
            ->groupBy('role_id')
            ->pluck('total', 'role_id');

        $activeCounts = (object) [
            'admin' => $rawCounts->get($roles->where('slug_identifier', 'admin')->first()?->id) ?? 0,
            'cwd'   => $rawCounts->get($roles->where('slug_identifier', 'cwd_officer')->first()?->id) ?? 0,
            'foreman' => $rawCounts->get($roles->where('slug_identifier', 'foreman')->first()?->id) ?? 0,
            'field_personnel' => $rawCounts->get($roles->where('slug_identifier', 'field_personnel')->first()?->id) ?? 0,
        ];

        $users = User::with('role')
            ->search($filters['search'] ?? null)
            ->filter($filters['filter'] ?? null)
            ->sort($filters['sort'] ?? null)
            ->paginate(10)
            ->withQueryString();

        return compact('users', 'roles', 'activeCounts');
    }

    public function getUserDetails(User $user): array
    {
        $user->load('department');

        $assignedTeams = $user->teams()
            ->with('department')
            ->withPivot('team_role_id', 'created_at')
            ->paginate(5);
            
        $teamRoles = TeamRole::pluck('role_name', 'id');

        $assignedTeams->getCollection()->transform(function ($team) use ($teamRoles) {
            $team->assigned_role_name = $teamRoles[$team->pivot->team_role_id] ?? 'Unknown Role';
            return $team;
        });

        return compact('assignedTeams');
    }

    public function processAndSaveUser(array $data, ?User $user = null): bool|User
    {
        $role = AccountRole::find($data['role_id']);
        
        // Field personnel are not tied directly to a single department
        if ($role && $role->slug_identifier === 'field_personnel') {
            $data['department_id'] = null;
        }

        if ($user) {
            $user->fill($data);
            if ($user->isClean()) return false;
            
            DB::transaction(fn() => $user->save());
            return true;
        }

        return User::create($data);
    }

    public function toggleUserStatus(User $user, bool $isActive): void
    {
        $user->is_active = $isActive;
        $user->save();
    }
}