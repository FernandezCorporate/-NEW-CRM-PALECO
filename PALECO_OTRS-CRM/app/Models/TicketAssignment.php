<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/*
 * Tracks the historical routing of a ticket to specific field teams.
 * Essential for calculating Service Level Agreements (SLAs) and turnaround times.
 */
class TicketAssignment extends Model
{

    protected $fillable = [
        'ticket_id',
        'team_id',
        'assigned_by',
        'reason',
        'unassigned_at'
    ];

    protected function casts(): array
    {
        return [
            'unassigned_at' => 'datetime',
        ];
    }

    // --- RELATIONSHIPS ---

    /*
     * Retrieves the ticket associated with this assignment log.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'system_id');
    }

    /*
     * Retrieves the field team that was assigned the task.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    /*
     * Retrieves the Foreman or CWD Officer who dispatched the ticket.
     */
    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}