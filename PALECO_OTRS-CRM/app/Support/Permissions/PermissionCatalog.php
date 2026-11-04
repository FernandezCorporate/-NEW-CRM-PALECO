<?php

namespace App\Support\Permissions;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Reads the permission list in config/permissions.php: which permissions exist,
 * which roles they apply to, and the defaults a new account receives.
 */
class PermissionCatalog
{
    /**
     * Permissions that stop tickets from moving when nobody in the department or team keeps them.
     */
    public const SUPERVISOR_WORKFLOW = ['tickets.assign', 'reports.verify'];

    public const FIELD_WORKFLOW = ['tickets.start', 'tickets.accomplish'];

    public static function all(): Collection
    {
        return collect(config('permissions'));
    }

    /**
     * Names of the permissions a role can hold; a new account of that role receives all of them.
     */
    public static function forRole(?string $roleSlug): array
    {
        if (! $roleSlug) {
            return [];
        }

        return self::all()
            ->filter(fn (array $meta) => in_array($roleSlug, $meta['roles'], true))
            ->keys()
            ->all();
    }

    /**
     * The checklist of a role, grouped by module: [module => [name => label]].
     */
    public static function grouped(?string $roleSlug = null): Collection
    {
        return self::all()
            ->filter(fn (array $meta) => $roleSlug === null || in_array($roleSlug, $meta['roles'], true))
            ->groupBy('module', preserveKeys: true)
            ->map(fn (Collection $items) => $items->map(fn (array $meta) => $meta['label']));
    }

    /**
     * Gives an account the default permissions of its role.
     */
    public static function grantDefaults(User $user): void
    {
        $ids = Permission::whereIn('name', self::forRole($user->role?->slug_identifier))->pluck('id');

        $user->permissions()->syncWithoutDetaching($ids);
        $user->unsetRelation('permissions');
    }

    /**
     * Whether an account differs from the defaults of its role.
     */
    public static function isCustomized(User $user): bool
    {
        $defaults = self::forRole($user->role?->slug_identifier);
        $held = $user->permissions->pluck('name')->all();

        return count(array_diff($defaults, $held)) > 0 || count(array_diff($held, $defaults)) > 0;
    }
}
