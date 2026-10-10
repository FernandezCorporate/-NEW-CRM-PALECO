<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stores client-driven idempotency keys, request hashes, and cached responses
 * to prevent duplicate operations in offline/asynchronous field sync scenarios.
 */
class IdempotencyRecord extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'idempotency_records';

    protected $fillable = [
        'user_id',
        'idempotency_key',
        'endpoint_path',
        'request_hash',
        'status',
        'response_code',
        'response_body',
        'expires_at',
    ];

    // --- CASTS ---

    /**
     * Defines the data type conversions for specific attributes.
     */
    protected function casts(): array
    {
        return [
            'response_code' => 'integer',
            'response_body' => 'array',
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    // --- RELATIONSHIPS ---

    /**
     * The authenticated user who initiated this idempotent request.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

