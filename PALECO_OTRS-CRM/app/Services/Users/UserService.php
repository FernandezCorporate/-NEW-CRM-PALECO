<?php

namespace App\Services\Users;

use App\Actions\Users\CreateUser;
use App\Actions\Users\ResetUserPassword;
use App\Actions\Users\ToggleUserActiveStatus;
use App\Actions\Users\UnlockUser;
use App\Actions\Users\UpdateUser;
use App\Models\AccountRole;
use App\Models\TeamRole;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service handling administration-level User queries and delegating mutations to Actions:
 * - CreateUser
 * - UpdateUser
 * - ResetUserPassword
 * - UnlockUser
 * - ToggleUserActiveStatus
 */
class UserService
{
    public function __construct(
        protected CreateUser $createUserAction,
        protected UpdateUser $updateUserAction,
        protected ResetUserPassword $resetUserPasswordAction,
        protected UnlockUser $unlockUserAction,
        protected ToggleUserActiveStatus $toggleUserActiveStatusAction
    ) {}

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
                $words = array_filter(explode(' ', strtolower($searchTerm)));
                $usersCollection = $usersCollection->filter(function (User $user) use ($words) {
                    $searchableText = strtolower(implode(' ', [
                        $user->username ?? '',
                        $user->first_name ?? '',
                        $user->middle_name ?? '',
                        $user->last_name ?? '',
                        $user->name_ext ?? '',
                        $user->email ?? '',
                        $user->contact ?? '',
                    ]));

                    foreach ($words as $word) {
                        if (! str_contains($searchableText, $word)) {
                            return false;
                        }
                    }

                    return true;
                });
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

    // --- MUTATING METHODS ---

    /**
     * Create or update a user record while handling concurrency locks and role constraints.
     */
    public function processAndSaveUser(array $data, ?User $user = null): array
    {
        if ($user) {
            return $this->updateUserAction->execute($user, $data);
        }

        $this->createUserAction->execute($data);

        return ['success' => true, 'changed' => true];
    }

    /**
     * Resets a user's password directly, revokes active sessions and tokens, and clears any active lockouts.
     */
    public function resetPassword(User $user, string $newPassword): void
    {
        $this->resetUserPasswordAction->execute($user, $newPassword);
    }

    /**
     * Administratively bypasses and clears an account security lockout.
     */
    public function unlockUser(User $user): void
    {
        $this->unlockUserAction->execute($user);
    }

    /**
     * Toggle the active status of a user and invalidate tokens/memberships if deactivated.
     *
     * @throws ValidationException
     */
    public function toggleUserStatus(User $user, bool $isActive): void
    {
        $this->toggleUserActiveStatusAction->execute($user, $isActive);
    }
}
