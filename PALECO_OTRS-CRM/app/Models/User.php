<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Enums\UserRoles;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;

#[Fillable(['username', 'first_name', 'middle_name', 'last_name', 'name_ext', 
'email', 'contact', 'role', 'password', 'last_login', 'locked_until'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasUlids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'role' => UserRoles::class,
            'last_login' => 'datetime',
            'locked_until' => 'datetime'
        ];
    }

    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: function () {
                $firstName = Str::title($this->first_name);
                $middleInitial = $this->middle_name ? strtoupper(substr($this->middle_name, 0, 1)) . '.' : '';
                $lastName = Str::title($this->last_name);
                $nameExt = $this->name_ext ? ', ' . strtoupper($this->name_ext) : '';

                $primaryName = implode(' ', array_filter([$firstName, $middleInitial, $lastName]));

                return $primaryName . $nameExt;
            }
        );
    }

    protected function avatarInitials(): Attribute
    {
        return Attribute::make(
            get: function () {
                $firstInitial = strtoupper(substr($this->first_name, 0, 1));
                $lastInitial = strtoupper(substr($this->last_name, 0, 1));

                return $firstInitial . $lastInitial;
            }
        );
    }

    protected function statusLabel(): Attribute
    {
        return Attribute::make(
            get: function () {
                $statusLabel = $this->is_active ? 'Active' : 'Deactivated';

                return $statusLabel;
            }
        );
    }
}
