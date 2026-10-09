<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Classifies the type of problem reported in a service ticket (e.g., Leak, Low Pressure).
 * Standardizes complaint types for operational reporting.
 */
#[Fillable(['category_name', 'category_desc'])]
class TicketCategory extends Model
{
    use Auditable, SoftDeletes;

    protected string $activityTitleAttribute = 'category_name';

    protected array $activityLogAttributes = ['category_name', 'category_desc'];

    // --- CASTS ---

    /**
     * Defines the data type conversions for specific attributes.
     */
    protected function casts(): array
    {
        return [
            'category_name' => 'string',
            'category_desc' => 'string',
        ];
    }

    // --- RELATIONSHIPS ---

    /**
     * Retrieves all service tickets classified under this specific category.
     */
    public function ticket(): HasMany
    {
        return $this->hasMany(Ticket::class, 'category_id');
    }

    // --- SCOPES ---

    /**
     * Applies a search filter against the category name and description.
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('category_name', 'like', "%{$search}%")
                ->orWhere('category_desc', 'like', "%{$search}%");
        });
    }

    /**
     * Applies sorting rules to the query based on the requested sort parameter.
     */
    public function scopeSort(Builder $query, ?string $sort): Builder
    {
        return match ($sort) {
            'oldest' => $query->oldest(),
            'category_nameASC' => $query->orderBy('category_name', 'asc'),
            'category_nameDESC' => $query->orderBy('category_name', 'desc'),
            default => $query->latest(),
        };
    }
}
