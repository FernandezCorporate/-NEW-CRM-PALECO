<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['role_name', 'slug_identifier'])]
class TeamRole extends Model
{
    use HasFactory;

    protected $table = 'team_roles';
}