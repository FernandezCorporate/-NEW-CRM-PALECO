<?php

namespace App\Services\Users;

use App\Models\AccountRole;
use App\Models\TeamRole;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Service handling administration-level User queries, in-memory decryption search, and profile data retrieval.
 */
class UserService
{
    // --- QUERY METHODS ---

    /**
     * Retrieve users matching filters along with role distribution counts.
     */
    public function getDashboardUsers(array $filters): array
    {
        $roles = AccountRole::orderBy('role_name')->get();

        $rawCounts = User::query()->where('is_active', true)
            ->select('role_id', DB::raw('count(*) as total'))
            ->groupBy('role_id')
            ->pluck('total', 'role_id');

        $roles->each(function ($role) use ($rawCounts) {
            $role->active_count = (int) ($rawCounts->get($role->id) ?? 0);
        });

        $activeCounts = (object) [
            'admin' => $rawCounts->get($roles->where('slug_identifier', 'admin')->first()?->id) ?? 0,
            'cwd' => $rawCounts->get($roles->where('slug_identifier', 'cwd_officer')->first()?->id) ?? 0,
            'supervisor' => $rawCounts->get($roles->where('slug_identifier', 'supervisor')->first()?->id) ?? 0,
            'field_personnel' => $rawCounts->get($roles->where('slug_identifier', 'field_personnel')->first()?->id) ?? 0,
        ];

        $query = User::with('role')
            ->filter($filters['filter'] ?? null);

        $searchTerm = trim($filters['search'] ?? '');
        $sort = $filters['sort'] ?? null;
        $isAlphabeticalSort = in_array($sort, ['first_nameASC', 'first_nameDESC', 'last_nameASC', 'last_nameDESC'], true);

        if ($searchTerm !== '' || $isAlphabeticalSort) {
            $usersCollection = $query->get();

            if ($searchTerm !== '') {
                $usersCollection = $usersCollection->filter(fn (User $user) => $user->matchesSearchTerm($searchTerm));
            }

            $usersCollection = match ($sort) {
                'oldest' => $usersCollection->sortBy('created_at'),
                'first_nameASC' => $usersCollection->sortBy('first_name', SORT_NATURAL | SORT_FLAG_CASE),
                'first_nameDESC' => $usersCollection->sortByDesc('first_name', SORT_NATURAL | SORT_FLAG_CASE),
                'last_nameASC' => $usersCollection->sortBy('last_name', SORT_NATURAL | SORT_FLAG_CASE),
                'last_nameDESC' => $usersCollection->sortByDesc('last_name', SORT_NATURAL | SORT_FLAG_CASE),
                default => $usersCollection->sortByDesc('created_at'),
            };

            $page = (int) request()->input('page', 1);
            $perPage = 10;
            $items = $usersCollection->slice(($page - 1) * $perPage, $perPage)->values();

            $users = new LengthAwarePaginator(
                $items,
                $usersCollection->count(),
                $perPage,
                $page,
                ['path' => request()->url(), 'query' => request()->query()]
            );
        } else {
            $users = $query->sort($sort)
                ->paginate(10)
                ->withQueryString();
        }

        return compact('users', 'roles', 'activeCounts');
    }

    /**
     * Retrieve user profile details and their assigned teams with pivot metadata.
     */
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

    /**
     * Retrieves the authenticated user and eagerly loads required relationships for API resource formatting.
     * Eagerly loads assigned operational teams when the authenticated user is a field personnel.
     */
    public function getProfileData(User $user): User
    {
        $user->load(['role', 'department']);

        if ($user->role?->slug_identifier === 'field_personnel') {
            $user->load(['teams.department']);
        }

        return $user;
    }
}
