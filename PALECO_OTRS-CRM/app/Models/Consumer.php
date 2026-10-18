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

    // --- CASTS ---

    /**
     * Defines attribute type casting.
     */
    protected function casts(): array
    {
        return [
            'name' => 'encrypted',
            'address' => 'encrypted',
        ];
    }

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

        $term = trim($term);
        $wildcard = "%{$term}%";

        // Query by account number or account code via SQL index
        $sqlMatches = static::query()
            ->where('acct_no', 'like', $wildcard)
            ->orWhere('acct_code', 'like', $wildcard)
            ->pluck('id');

        // Query by decrypted name
        $nameMatches = static::all()->filter(function (Consumer $consumer) use ($term) {
            return str_contains(mb_strtolower($consumer->name ?? ''), mb_strtolower($term));
        })->pluck('id');

        $allIds = $sqlMatches->merge($nameMatches)->unique();

        return $query->whereIn('id', $allIds);
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
                $matchingIds = static::all()->filter(function (Consumer $consumer) use ($last, $first) {
                    $lowerName = mb_strtolower($consumer->name ?? '');
                    $matchesLast = str_contains($lowerName, mb_strtolower($last));
                    $matchesFirst = empty($first) || str_contains($lowerName, mb_strtolower($first));

                    return $matchesLast && $matchesFirst;
                })->pluck('id');
            } else {
                $matchingIds = static::all()->filter(function (Consumer $consumer) use ($lastName) {
                    return str_contains(mb_strtolower($consumer->name ?? ''), mb_strtolower($lastName));
                })->pluck('id');
            }

            $query->whereIn('id', $matchingIds);
        }

        if (! empty($meterSerial)) {
            $query->where('meter_serial', 'like', '%'.trim($meterSerial).'%');
        }

        return $query;
    }
}
