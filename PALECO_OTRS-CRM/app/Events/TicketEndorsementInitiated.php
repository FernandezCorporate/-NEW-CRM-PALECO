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
     * The ticket's department and crew are told it is frozen, not only CWD.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $ticket = $this->endorsement->ticket;
        $channels = [new PrivateChannel('cwd.operations')];

        if ($ticket?->team_id) {
            $channels[] = new PrivateChannel('team.'.$ticket->team_id);
        }

        if ($ticket?->department_id) {
            $channels[] = new PrivateChannel('department.'.$ticket->department_id);
        }

        return $channels;
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
            'team_id' => $this->endorsement->ticket?->team_id,
            'department_id' => $this->endorsement->ticket?->department_id,
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
