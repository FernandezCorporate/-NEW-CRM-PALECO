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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use App\Models\Department;

#[Fillable(['username', 'first_name', 'middle_name', 'last_name', 'name_ext', 
'email', 'contact', 'role', 'password', 'department_id', 'last_login', 'locked_until'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasUlids, LogsActivity;

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

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
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

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (empty($term)) {
            return $query;
        }

        $words = array_filter(explode(' ', $term));

        return $query->where(function ($query) use ($words) {

            foreach ($words as $word) {
                $searchWord = "%{$word}%";
                
                $query->where(function ($subQuery) use ($searchWord) {
                    $subQuery->where('first_name', 'like', $searchWord)
                          ->orWhere('middle_name', 'like', $searchWord)
                          ->orWhere('last_name', 'like', $searchWord)
                          ->orWhere('name_ext', 'like', $searchWord)
                          ->orWhere('email', 'like', $searchWord)
                          ->orWhere('username', 'like', $searchWord);
                });
            }
        });
    }

    public function scopeFilter(Builder $query, ?string $filter): Builder
    {
        if ($filter === 'all') {
            return $query;
        }

        return $query->where('role', $filter);
    }

    public function scopeSort(Builder $query, ?string $sort): Builder
    {
        if (empty($sort)) {
            return $query;
        }

        switch ($sort) {
            case 'newest':
                return $query->orderBy('created_at', 'desc');
            case 'oldest':
                return $query->orderBy('created_at', 'asc');
            case 'first_nameASC':
                return $query->orderBy('first_name', 'asc');
            case 'first_nameDESC':
                return $query->orderBy('first_name', 'desc');
            case 'last_nameASC':
                return $query->orderBy('last_name', 'asc');
            case 'last_nameDESC':
                return $query->orderBy('last_name', 'desc');
            default:
                return $query;
        }
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('Users')
            ->logOnly(['username', 'first_name', 'middle_name', 'last_name', 'name_ext', 
                        'email', 'contact', 'role', 'password', 'department_id'])
            ->setDescriptionForEvent(function(string $eventName) {
                return "{$this->username} account has been {$eventName}";
            });
    }
}
