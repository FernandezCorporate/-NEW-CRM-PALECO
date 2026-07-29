<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\User;

/*
 * Represents an operational unit or field team assigned to resolve tickets.
 * Teams exist within a department and contain multiple assigned users.
 */
#[Fillable(['team_name', 'team_desc', 'shift_start', 'shift_end', 'department_id'])]
class Team extends Model
{
    use HasUlids, SoftDeletes, LogsActivity;

    protected function casts(): array
    {
        return [
            'shift_start' => 'datetime:H:i',
            'shift_end' => 'datetime:H:i',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members')
            ->withPivot('team_role_id')
            ->withTimestamps();
    }

    public function ticket(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    // --- STANDARD WEB SCOPE FUNCTIONS ---

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (empty($term)) return $query;
        $words = array_filter(explode(' ', $term));
        return $query->where(function ($query) use ($words) {
            foreach ($words as $word) {
                $searchWord = "%{$word}%";
                $query->where(function ($subQuery) use ($searchWord) {
                    $subQuery->where('team_name', 'like', $searchWord)
                          ->orWhere('team_desc', 'like', $searchWord)
                          ->orWhere('shift_start', 'like', $searchWord)
                          ->orWhere('shift_end', 'like', $searchWord);
                });
            }
        });
    }

    public function scopeFilter(Builder $query, ?string $filter): Builder
    {
        if (empty($filter) || $filter === 'all') return $query;
        return $query->where('department_id', $filter);
    }

    public function scopeSort(Builder $query, ?string $sort): Builder
    {
        return match ($sort) {
            'oldest' => $query->oldest(),
            'team_nameASC' => $query->orderBy('team_name', 'asc'),
            'team_nameDESC' => $query->orderBy('team_name', 'desc'),
            default => $query->latest(),
        };
    }

    // --- MOBILE API SCOPE FUNCTIONS ---

    public function scopeApiSearch(Builder $query, ?string $term): Builder
    {
        if (empty($term)) return $query;
        $words = array_filter(explode(' ', $term));
        return $query->where(function ($query) use ($words) {
            foreach ($words as $word) {
                $searchWord = "%{$word}%";
                $query->where(function ($subQuery) use ($searchWord) {
                    $subQuery->where('team_name', 'like', $searchWord)
                          ->orWhere('team_desc', 'like', $searchWord)
                          ->orWhere('shift_start', 'like', $searchWord)
                          ->orWhere('shift_end', 'like', $searchWord);
                });
            }
        });
    }

    /*
     * Dedicated API scope to filter teams by their lifecycle status.
     */
    public function scopeApiFilterStatus(Builder $query, ?string $status): Builder
    {
        return match ($status) {
            'archive', 'archived' => $query->onlyTrashed(), // Supports ?filter=archive
            'all'                 => $query->withTrashed(),   
            'active'              => $query->whereNull('deleted_at'),
            default               => $query->whereNull('deleted_at'), // Safely defaults to active
        };
    }

    public function scopeApiSort(Builder $query, ?string $sort): Builder
    {
        return match ($sort) {
            'oldest' => $query->oldest(),
            'team_nameASC' => $query->orderBy('team_name', 'asc'),
            'team_nameDESC' => $query->orderBy('team_name', 'desc'),
            'shift_startASC' => $query->orderBy('shift_start', 'asc'),
            'shift_startDESC' => $query->orderBy('shift_start', 'desc'),
            'shift_endASC' => $query->orderBy('shift_end', 'asc'),
            'shift_endDESC' => $query->orderBy('shift_end', 'desc'),
            default => $query->latest(), 
        };
    }

    // --- ACTIVITY LOG ---

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('Teams')
            ->logOnly(['team_name', 'team_desc', 'shift_start', 'shift_end', 'department_id'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(function(string $eventName) {
                $action = match($eventName) {
                    'deleted'  => $this->isForceDeleting() ? 'permanently deleted' : 'archived',
                    'restored' => 'restored',
                    default    => $eventName,
                };
                return "{$this->team_name} has been {$action}";
            });
    }
}