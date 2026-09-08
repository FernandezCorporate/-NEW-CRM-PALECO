<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Models\User;
use App\Models\Ticket;

/*
 * Represents a single chronological communication update on a ticket.
 */
#[Fillable(['ticket_id', 'user_id', 'body', 'is_internal'])]
class TicketRemark extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'ticket_remarks';

    // --- CASTS ---

    /*
     * Defines the data type conversions for specific attributes.
     */
    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
            'created_at'  => 'datetime',
            'updated_at'  => 'datetime',
        ];
    }

    // --- RELATIONSHIPS ---

    public function ticket(): BelongsTo
    {
        // Assumes your tickets table still uses system_id as its primary key
        return $this->belongsTo(Ticket::class, 'ticket_id', 'system_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}