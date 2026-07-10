<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable('category_name', 'category_desc')]
class TicketCategory extends Model
{
    use LogsActivity, SoftDeletes;

    protected function casts(): array
    {
        return [
            'category_name' => 'string',
            'category_desc' => 'string',
        ];
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('category_name', 'like', "%{$search}%")
              ->orWhere('category_desc', 'like', "%{$search}%");
        });
    }

    public function scopeSort($query, $sort)
    {
        return match ($sort) {
            'oldest' => $query->oldest(),
            'category_nameASC' => $query->orderBy('category_name', 'asc'),
            'category_nameDESC' => $query->orderBy('category_name', 'desc'),
            default => $query->latest(), // 'newest'
        };
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('TicketCategory')
            ->logOnly(['category_name', 'category_desc'])
            ->setDescriptionForEvent(function(string $eventName) {
                $action = match ($eventName) {
                    'deleted'      => $this->isForceDeleting() ? 'permanently deleted' : 'archived',
                    'restored'     => 'restored',
                    default        => $eventName, // Fallback for 'created' and 'updated'
                };

                return "{$this->category_name} has been {$action}";
            });
    }
}
