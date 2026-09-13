<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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
}