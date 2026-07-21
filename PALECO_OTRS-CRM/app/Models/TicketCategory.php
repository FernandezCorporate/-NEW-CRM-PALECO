<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

use App\Models\Ticket;

/*
 * Classifies the type of problem reported in a service ticket (e.g., Leak, Low Pressure).
 * Standardizes complaint types for operational reporting.
 */
#[Fillable(['category_name', 'category_desc'])]
class TicketCategory extends Model
{
    use LogsActivity, SoftDeletes;

    // --- CASTS ---

    /*
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

    /*
     * Retrieves all service tickets classified under this specific category.
     */
    public function ticket(): HasMany
    {
        return $this->hasMany(Ticket::class, 'category_id');
    }

    // --- SCOPE FUNCTIONS ---

    /*
     * Applies a search filter against the category name and description.
     */
    public function scopeSearch($query, $search)
    {
        if (empty($search)) return $query;

        return $query->where(function ($q) use ($search) {
            $q->where('category_name', 'like', "%{$search}%")
              ->orWhere('category_desc', 'like', "%{$search}%");
        });
    }

    /*
     * Applies sorting rules to the query based on the requested sort parameter.
     */
    public function scopeSort($query, $sort)
    {
        return match ($sort) {
            'oldest' => $query->oldest(),
            'category_nameASC' => $query->orderBy('category_name', 'asc'),
            'category_nameDESC' => $query->orderBy('category_name', 'desc'),
            default => $query->latest(),
        };
    }

    // --- ACTIVITY LOG ---

    /*
     * Configures the Spatie Activitylog options for this model.
     * Records additions, edits, and archive events for system categories.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('TicketCategory')
            ->logOnly(['category_name', 'category_desc'])
            ->setDescriptionForEvent(function(string $eventName) {
                $action = match ($eventName) {
                    'deleted'  => $this->isForceDeleting() ? 'permanently deleted' : 'archived',
                    'restored' => 'restored',
                    default    => $eventName,
                };
                return "{$this->category_name} has been {$action}";
            });
    }
}