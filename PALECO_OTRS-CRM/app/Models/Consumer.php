<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
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
        'meter_serial',
    ];

    // --- ACCESSORS & MUTATORS ---

    /**
     * Ensure consumer status is always stored and accessed as human-readable text.
     */
    protected function status(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => match (strtoupper(trim($value ?? ''))) {
                'A', 'ACTIVE' => 'Active',
                'D', 'DEACTIVATED' => 'Deactivated',
                'I', 'INACTIVE' => 'Inactive',
                default => empty($value) ? 'Unknown' : (in_array(ucfirst(strtolower($value)), ['Active', 'Deactivated', 'Inactive']) ? ucfirst(strtolower($value)) : 'Unknown'),
            },
            set: fn (?string $value) => match (strtoupper(trim($value ?? ''))) {
                'A', 'ACTIVE' => 'Active',
                'D', 'DEACTIVATED' => 'Deactivated',
                'I', 'INACTIVE' => 'Inactive',
                default => 'Unknown',
            },
        );
    }

    // --- RELATIONSHIPS ---

    /**
     * All tickets associated with this consumer profile.
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'consumer_id');
    }

    // --- SCOPES ---

    /**
     * Search consumers by name, account number, or account code.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (empty($term)) {
            return $query;
        }

        $term = "%{$term}%";

        return $query->where(function ($query) use ($term) {
            $query->where('name', 'like', $term)
                ->orWhere('acct_no', 'like', $term)
                ->orWhere('acct_code', 'like', $term);
        });
    }

    /**
     * Search consumers by name (supporting Lastname,Firstname) or meter serial.
     */
    public function scopeSearchByNameAndMeter(Builder $query, ?string $lastName = null, ?string $meterSerial = null): Builder
    {
        if (! empty($lastName)) {
            $lastName = trim($lastName);

            if (str_contains($lastName, ',')) {
                [$last, $first] = array_map('trim', explode(',', $lastName, 2));
                $query->where('name', 'like', "%{$last}%");
                if (! empty($first)) {
                    $query->where('name', 'like', "%{$first}%");
                }
            } else {
                $query->where('name', 'like', "%{$lastName}%");
            }
        }

        if (! empty($meterSerial)) {
            $query->where('meter_serial', 'like', '%' . trim($meterSerial) . '%');
        }

        return $query;
    }
}
