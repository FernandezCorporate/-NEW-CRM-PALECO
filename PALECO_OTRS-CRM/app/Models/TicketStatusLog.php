<?php

namespace App\Models;

use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Provides an immutable audit trail of all lifecycle status changes for a Ticket.
 * Records the exact state transitions and the user responsible for them.
 */
class TicketStatusLog extends Model
{
    protected $fillable = [
        'ticket_id',
        'changed_by_id',
        'old_status',
        'new_status',
    ];

    // --- CASTS ---

    /**
     * Forces the state variables to resolve into TicketStatus enum instances.
     */
    protected function casts(): array
    {
        return [
            'old_status' => TicketStatus::class,
            'new_status' => TicketStatus::class,
        ];
    }

    // --- RELATIONSHIPS ---

    /**
     * Retrieves the specific parent ticket that this log entry refers to.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    /**
     * Retrieves the user who executed the status change.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_id');
    }
}
