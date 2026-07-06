<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
            ->withPivot('team_role')
            ->withTimestamps();
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
        // Check if the filter is completely empty OR set to 'all'
        if (empty($filter) || $filter === 'all') {
            return $query;
        }

        return $query->where('department_id', $filter);
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
            case 'team_nameASC':
                return $query->orderBy('team_name', 'asc');
            case 'team_nameDESC':
                return $query->orderBy('team_name', 'desc');
            case 'shift_startASC':
                return $query->orderBy('shift_start', 'asc');
            case 'shift_startDESC':
                return $query->orderBy('shift_start', 'desc');
            case 'shift_endASC':
                return $query->orderBy('shift_end', 'asc');
            case 'shift_endDESC':
                return $query->orderBy('shift_end', 'desc');
            default:
                return $query;
        }
    }

    public function getActivitylogOptions(): LogOptions
        {
            return LogOptions::defaults()
                ->useLogName('Users')
                ->logOnly([
                    'team_name', 'team_desc', 'shift_start',
                    'shift_end', 'department_id'
                ])
                ->logOnlyDirty()
                ->setDescriptionForEvent(function(string $eventName) {
                    $action = match($eventName) {
                        'deleted'      => $this->isForceDeleting() ? 'permanently deleted' : 'archived',
                        'restored'     => 'restored',
                        default        => $eventName,
                    };

                    return "{$this->team_name} has been {$action}";
                });
        }
}
