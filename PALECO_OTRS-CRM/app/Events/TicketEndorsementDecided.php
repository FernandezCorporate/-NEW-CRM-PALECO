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
 * Real-time event broadcast immediately upon a CWD officer deciding an endorsement request (Approved / Rejected).
 */
class TicketEndorsementDecided implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public TicketEndorsement $endorsement,
        public ?string $childTicketId = null,
        public ?string $childTicketNumber = null,
        public ?string $previousTeamId = null
    ) {}

    /**
     * Get the channels the event should broadcast on.
     * Approval clears the crew from the parent, so the crew that had it is passed in as $previousTeamId.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $ticket = $this->endorsement->ticket;
        $channels = [new PrivateChannel('cwd.operations')];

        $teamId = $ticket?->team_id ?? $this->previousTeamId;
        if ($teamId) {
            $channels[] = new PrivateChannel('team.'.$teamId);
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
            'decision' => $this->endorsement->status->value,
            'decision_label' => $this->endorsement->status->label(),
            'rejection_reason' => $this->endorsement->rejection_reason,
            'parent_status' => $this->endorsement->ticket?->status->value,
            'parent_status_label' => $this->endorsement->ticket?->status->label(),
            'child_ticket_id' => $this->childTicketId,
            'child_ticket_number' => $this->childTicketNumber,
            'team_id' => $this->endorsement->ticket?->team_id,
            'previous_team_id' => $this->previousTeamId,
            'department_id' => $this->endorsement->ticket?->department_id,
            'new_department_id' => $this->endorsement->new_department_id,
            'reviewed_by' => $this->endorsement->reviewer ? "{$this->endorsement->reviewer->first_name} {$this->endorsement->reviewer->last_name}" : 'CWD Officer',
            'reviewed_at' => $this->endorsement->reviewed_at?->format('M d, Y h:i A') ?? now()->format('M d, Y h:i A'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'TicketEndorsementDecided';
    }
}
