<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

use Laravel\Sanctum\HasApiTokens;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

use App\Models\AccountRole;
use App\Models\Department;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketStatusLog;

/*
 * Represents an authenticated individual within the system.
 * Manages credentials, roles, organizational assignments, and activity tracking.
 */
#[Fillable(['username', 'first_name', 'middle_name', 'last_name', 'name_ext', 'email', 'contact', 'role_id', 'password', 'department_id', 'last_login', 'locked_until', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasFactory, Notifiable, HasUlids, LogsActivity, HasApiTokens;

    // --- CASTS ---

    /*
     * Defines strict datatype conversions and secures the password format.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login' => 'datetime',
            'locked_until' => 'datetime'
        ];
    }

    // --- RELATIONSHIPS ---

    /*
     * Retrieves the global access role assigned to this user account.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(AccountRole::class, 'role_id');
    }

    /*
     * Retrieves the primary department this user operates under.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /*
     * Retrieves the operational teams this user is deployed to, including their pivot roles.
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members')
            ->withPivot('team_role_id')
            ->withTimestamps();
    }

    /*
     * Retrieves all service tickets originally authored by this user.
     */
    public function ticket(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /*
     * Retrieves all ticket status changes enacted by this user.
     */
    public function ticketStatus(): HasMany
    {
        return $this->hasMany(TicketStatusLog::class, 'changed_by');
    }

    public function escalations(): HasMany
    {
        return $this->hasMany(TicketEscalation::class, 'created_by');
    }

    // --- ACCESSORS ---

    /*
     * Concatenates and properly formats the user's full legal name.
     */
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: function () {
                $firstName = Str::title($this->first_name);
                $middleInitial = $this->middle_name ? strtoupper(substr($this->middle_name, 0, 1)) . '.' : '';
                $lastName = Str::title($this->last_name);
                $nameExt = $this->name_ext ? ', ' . strtoupper($this->name_ext) : '';

                return implode(' ', array_filter([$firstName, $middleInitial, $lastName])) . $nameExt;
            }
        );
    }

    /*
     * Extracts the first initials of the user's first and last name for frontend avatars.
     */
    protected function avatarInitials(): Attribute
    {
        return Attribute::make(
            get: fn () => strtoupper(substr($this->first_name, 0, 1) . substr($this->last_name, 0, 1))
        );
    }

    /*
     * Converts the boolean active state into a human-readable label.
     */
    protected function statusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->is_active ? 'Active' : 'Deactivated'
        );
    }

    // --- SCOPE FUNCTIONS ---

    /*
     * Applies a multi-word search filter against names, emails, and usernames.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (empty($term)) return $query;

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

    /*
     * Applies a filter to restrict users based on their assigned role identifier.
     */
    public function scopeFilter(Builder $query, ?string $filter): Builder
    {
        if (empty($filter) || $filter === 'all') return $query;

        return $query->whereHas('role', fn ($q) => $q->where('slug_identifier', $filter));
    }

    /*
     * Applies sorting rules to the query based on chronology or alphabetical names.
     */
    public function scopeSort(Builder $query, ?string $sort): Builder
    {
        return match ($sort) {
            'oldest' => $query->oldest(),
            'first_nameASC' => $query->orderBy('first_name', 'asc'),
            'first_nameDESC' => $query->orderBy('first_name', 'desc'),
            'last_nameASC' => $query->orderBy('last_name', 'asc'),
            'last_nameDESC' => $query->orderBy('last_name', 'desc'),
            default => $query->latest(),
        };
    }

    // --- ACTIVITY LOG ---

    /*
     * Configures the Spatie Activitylog options for this model.
     * Records critical changes to account details and captures activation states.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('Users')
            ->logOnly([
                'username', 'first_name', 'middle_name', 'last_name', 'name_ext', 
                'email', 'contact', 'role_id', 'department_id', 'is_active'
            ])
            ->logOnlyDirty()
            ->setDescriptionForEvent(function(string $eventName) {
                if ($eventName === 'updated' && $this->wasChanged('is_active')) {
                    return $this->is_active ? "{$this->username} account has been reactivated" : "{$this->username} account has been deactivated";
                }
                return "{$this->username} account has been {$eventName}";
            });
    }
}