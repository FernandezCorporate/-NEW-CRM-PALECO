<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
/*
 * Represents a cached utility consumer retrieved from the external CRM API.
 */
class Consumer extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = [
        'acct_no',
        'acct_code',
        'name',
        'address',
        'status',
        'meter_serial'
    ];

    // --- RELATIONSHIPS ---

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'consumer_id');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (empty($term)) return $query;
        $term = "%$term%";
        return $query->where(function ($query) use ($term) {
            $query->where('name', 'like', $term)
                  ->orWhere('acct_no', 'like', $term)
                  ->orWhere('acct_code', 'like', $term);
        });
    }
}