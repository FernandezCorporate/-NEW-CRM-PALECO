<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;

#[Fillable(['role_name', 'role_desc', 'slug_identifier'])]
class Role extends Model
{
    use SoftDeletes, HasFactory;

    protected $table = 'account_roles';

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role_id');
    }
}
