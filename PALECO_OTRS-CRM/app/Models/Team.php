<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Represents an operational unit or field team assigned to resolve tickets.
 * Teams exist within a department and contain multiple assigned users.
 */
#[Fillable(['team_name', 'team_desc', 'shift_start', 'shift_end', 'department_id'])]
class Team extends Model
{
    use Auditable, HasFactory, HasUlids, SoftDeletes;

    protected string $activityLogName = 'Teams';

    protected string $activityTitleAttribute = 'team_name';

    protected array $activityLogAttributes = ['team_name', 'team_desc', 'shift_start', 'shift_end', 'department_id'];

    // --- CASTS ---

    /**
     * Defines attribute type casting.
     */
    protected function casts(): array
    {
        return [
            'shift_start' => 'datetime:H:i',
            'shift_end' => 'datetime:H:i',
        ];
    }

    // --- RELATIONSHIPS ---

    /**
     * The department this team belongs to.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * The user members assigned to this team.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members')
            ->withPivot('team_role_id')
            ->withTimestamps();
    }

    /**
     * The service tickets assigned to this team.
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Backward-compatible alias for tickets().
     */
    public function ticket(): HasMany
    {
        return $this->tickets();
    }

    // --- SCOPES (UNIFIED) ---

    /**
     * Unified scope: Search teams across words in name, description, and shifts.
     */
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
                    $subQuery->where('team_name', 'like', $searchWord)
                        ->orWhere('team_desc', 'like', $searchWord)
                        ->orWhere('shift_start', 'like', $searchWord)
                        ->orWhere('shift_end', 'like', $searchWord);
                });
            }
        });
    }

    /**
     * Unified scope: Filter teams by department ID.
     */
    public function scopeFilter(Builder $query, ?string $filter): Builder
    {
        if (empty($filter) || $filter === 'all') {
            return $query;
        }

        return $query->where('department_id', $filter);
    }

    /**
     * Unified scope: Filter teams by lifecycle status.
     * Supports 'archive'/'archived' (only trashed), 'all' (with trashed), 'active' (active only).
     */
    public function scopeFilterByStatus(Builder $query, ?string $status): Builder
    {
        return match ($status) {
            'archive', 'archived' => $query->onlyTrashed(),
            'all' => $query->withTrashed(),
            'active' => $query->whereNull('deleted_at'),
            default => $status ? $query->whereNull('deleted_at') : $query,
        };
    }

    /**
     * Unified scope: Sort teams.
     */
    public function scopeSort(Builder $query, ?string $sort): Builder
    {
        return match ($sort) {
            'oldest' => $query->oldest(),
            'name_asc', 'team_nameASC' => $query->orderBy('team_name', 'asc'),
            'name_desc', 'team_nameDESC' => $query->orderBy('team_name', 'desc'),
            'shift_startASC' => $query->orderBy('shift_start', 'asc'),
            'shift_startDESC' => $query->orderBy('shift_start', 'desc'),
            'shift_endASC' => $query->orderBy('shift_end', 'asc'),
            'shift_endDESC' => $query->orderBy('shift_end', 'desc'),
            default => $query->latest(),
        };
    }
}
