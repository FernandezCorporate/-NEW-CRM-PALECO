<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

use App\Models\Team;
use App\Models\User;
use App\Models\Ticket;

/*
 * Represents a primary organizational unit within the cooperative.
 * Departments contain both individual users and operational teams.
 */
#[Fillable(['dept_name', 'dept_desc'])]
class Department extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    // --- CASTS ---

    /*
     * Defines the data type conversions for specific attributes.
     */
    protected function casts(): array
    {
        return [
            'dept_name' => 'string',
            'dept_desc' => 'string',
        ];
    }

    // --- RELATIONSHIPS ---

    /*
     * Retrieves all users directly assigned to this department.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'department_id');
    }

    /*
     * Retrieves all operational teams belonging to this department.
     */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class, 'department_id');
    }

    /*
     * Retrieves all tickets assigned to this department.
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'department_id');
    }

    // --- SCOPE FUNCTIONS ---

    /*
     * Applies a search filter to the query based on department name or description.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (empty($term)) return $query;

        $term = "%$term%";
        return $query->where(function ($query) use ($term) {
            $query->where('dept_name', 'like', $term)
                  ->orWhere('dept_desc', 'like', $term);
        });
    }

    /*
     * Applies sorting rules to the query based on the requested sort parameter.
     */
    public function scopeSort(Builder $query, ?string $sort): Builder
    {
        return match ($sort) {
            'newest' => $query->latest(),
            'oldest' => $query->oldest(),   
            'dept_nameASC' => $query->orderBy('dept_name', 'asc'),
            'dept_nameDESC' => $query->orderBy('dept_name', 'desc'),
            'dept_descASC' => $query->orderBy('dept_desc', 'asc'),
            'dept_descDESC' => $query->orderBy('dept_desc', 'desc'),
            default => $query->latest(),
        };
    }

    // --- ACTIVITY LOG ---

    /*
     * Configures the Spatie Activitylog options for this model.
     * Customizes the log description based on the action performed.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('Department')
            ->logOnly(['dept_name', 'dept_desc'])
            ->setDescriptionForEvent(function(string $eventName) {
                $action = match ($eventName) {
                    'deleted'  => $this->isForceDeleting() ? 'permanently deleted' : 'archived',
                    'restored' => 'restored',
                    default    => $eventName,
                };
                return "{$this->dept_name} has been {$action}";
            });
    }
}