<?php

namespace App\Events;

use App\Models\TicketEndorsement;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Real-time event broadcast immediately upon requesting a ticket endorsement.
 */
class TicketEndorsementInitiated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public TicketEndorsement $endorsement) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        return new PrivateChannel('cwd.operations');
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'ticket_id' => $this->endorsement->ticket_id,
            'ticket_number' => $this->endorsement->ticket?->ticket_number,
            'parent_ticket_id' => $this->endorsement->ticket?->parent_ticket_id,
            'endorsement_id' => $this->endorsement->id,
            'status' => 'pending_endorsement',
            'status_label' => 'Pending Endorsement',
            'suggested_department_id' => $this->endorsement->suggested_department_id,
            'suggested_department_name' => $this->endorsement->suggestedDepartment?->dept_name ?? 'Not specified',
            'reason' => $this->endorsement->reason,
            'requested_by' => $this->endorsement->creator ? "{$this->endorsement->creator->first_name} {$this->endorsement->creator->last_name}" : 'Supervisor',
            'created_at' => $this->endorsement->created_at?->format('M d, Y h:i A') ?? now()->format('M d, Y h:i A'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'TicketEndorsementInitiated';
    }
}
