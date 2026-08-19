<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Enums\TicketAccomplishmentStatus;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;


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
        'rejection_reason'
    ];

    protected function casts(): array
    {
        return [
            'accomplished_at' => 'datetime',
            'status' => TicketAccomplishmentStatus::class,
        ];
    }

    // --- RELATIONSHIPS ---

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function accomplishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accomplished_by_id');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }
}