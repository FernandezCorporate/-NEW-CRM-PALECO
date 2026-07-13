<?php

namespace App\Models;

use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketStatusLog extends Model
{
    protected $fillable = [
        'ticket_id',
        'changed_by',
        'old_status',
        'new_status'
    ];

    protected function casts(): array
    {
        return [
            'old_status' => TicketStatus::class,
            'new_status' => TicketStatus::class
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'system_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by', 'id');
    }
}