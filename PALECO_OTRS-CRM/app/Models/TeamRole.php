<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/*
 * Defines the specific roles users can hold within a Team (e.g., Foreman, Member).
 * Stored in the pivot table when a User is assigned to a Team.
 */
#[Fillable(['role_name', 'slug_identifier'])]
class TeamRole extends Model
{
    use HasFactory;

    protected $table = 'team_roles';
}