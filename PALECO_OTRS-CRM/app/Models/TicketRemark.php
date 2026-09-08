<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketRemark extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'ticket_remarks';

    /**
     * Primary key configuration for ULID.
     */
    protected $primaryKey = 'system_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'ticket_id',
        'user_id',
        'body',
        'is_internal',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    /**
     * The ticket that owns this remark.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'system_id');
    }

    /**
     * The user who authored the remark.
     */
    public function author(): BelongsTo
    {
        // Adjust third argument if your User model uses 'system_id' instead of 'id'
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}