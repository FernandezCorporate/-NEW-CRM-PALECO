<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;
use App\Models\AccountRole;
use App\Models\TeamRole;
use App\Models\User;

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

    public function processAndSaveUser(array $data, ?User $user = null): array
    {
        $originalUpdatedAt = $data['original_updated_at'] ?? null;
        unset($data['original_updated_at']);

        $role = $user ? $user->role : AccountRole::find($data['role_id']);
        
        if ($role && $role->slug_identifier === 'field_personnel') {
            $data['department_id'] = null;
        }

        if ($user) {
            return DB::transaction(function () use ($user, $data, $originalUpdatedAt) {
                $lockedUser = User::query()->where('id', $user->id)->lockForUpdate()->first();

                if ((string) $lockedUser->updated_at !== $originalUpdatedAt) {
                    return ['success' => false, 'message' => 'Conflict: This user profile was modified by another admin while you were editing.'];
                }

                $lockedUser->fill($data);
                
                if ($lockedUser->isClean()) return ['success' => true, 'changed' => false];
                
                $lockedUser->save(); 
                return ['success' => true, 'changed' => true];
            });
        }

        User::create($data);
        return ['success' => true, 'changed' => true]; 
    }

    public function toggleUserStatus(User $user, bool $isActive): void
    {
        DB::transaction(function () use ($user, $isActive) {
            $user->is_active = $isActive;
            
            if (!$isActive) {
                $user->tokens()->delete();

                if ($user->role->slug_identifier === 'field_personnel') {
                    $user->teams()->detach();
                }
            }

            $user->save();
        });
    }
}