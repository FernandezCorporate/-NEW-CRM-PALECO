<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;

#[Fillable(['role_name', 'slug_identifier'])]
class AccountRole extends Model
{
    use HasFactory;

    protected $table = 'account_roles';

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role_id');
    }
}