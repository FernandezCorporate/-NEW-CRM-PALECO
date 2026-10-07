<?php

namespace App\Models;

use App\Enums\TicketAccomplishmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a work completion report submitted by field personnel for a ticket.
 */
class TicketAccomplishment extends Model
{
    protected $fillable = [
        'ticket_id',
        'accomplished_by_id',
        'remarks',
        'accomplished_at',
        'signature_path',
        'consumer_name',
        'status',
        'approved_by_id',
        'rejected_by_id',
        'rejection_reason',
    ];

    // --- CASTS ---

    /**
     * Defines attribute type casting.
     */
    protected function casts(): array
    {
        return [
            'accomplished_at' => 'datetime',
            'status' => TicketAccomplishmentStatus::class,
        ];
    }

    // --- RELATIONSHIPS ---

    /**
     * The ticket this accomplishment report applies to.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    /**
     * The field personnel who submitted this accomplishment report.
     */
    public function accomplishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accomplished_by_id');
    }

    /**
     * The supervisor who rejected this accomplishment report (if rejected).
     */
    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_id');
    }

    /**
     * The supervisor who approved this accomplishment report (if approved).
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    /**
     * Photo attachments documenting proof of work.
     */
    public function photos(): HasMany
    {
        return $this->hasMany(AccomplishmentPhoto::class, 'accomplishment_id');
    }
}
