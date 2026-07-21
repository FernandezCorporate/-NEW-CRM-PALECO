<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Models\User;

/*
 * Defines the core access roles within the system.
 * Used for Role-Based Access Control (RBAC) to determine user permissions.
 */
#[Fillable(['role_name', 'slug_identifier'])]
class AccountRole extends Model
{
    use HasFactory;

    protected $table = 'account_roles';

    // --- RELATIONSHIPS ---

    /*
     * Retrieves all user accounts associated with this specific role.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role_id');
    }
}