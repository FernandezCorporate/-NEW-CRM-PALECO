<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A single action a user can be allowed to perform, such as "tickets.cancel".
 * The list is defined in config/permissions.php and seeded by migration; it is not edited from the interface.
 */
#[Fillable(['name', 'label', 'module'])]
class Permission extends Model
{
    protected $table = 'permissions';

    // --- RELATIONSHIPS ---

    /**
     * Retrieves the user accounts that hold this permission.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'permission_user');
    }
}
