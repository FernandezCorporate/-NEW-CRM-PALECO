<?php

namespace App\Events;

use App\Models\Ticket;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Real-time event broadcast immediately upon the administrative force-closure of an active ticket.
 */
class TicketForceClosed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ?string $targetTeamId;

    /**
     * Create a new event instance.
     */
    public function __construct(public Ticket $ticket, ?string $teamId = null)
    {
        $this->targetTeamId = $teamId ?? $ticket->team_id;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('cwd.operations')];

        $teamId = $this->targetTeamId ?? $this->ticket->team_id;
        if ($teamId) {
            $channels[] = new PrivateChannel('team.'.$teamId);
        }

        if ($this->ticket->department_id) {
            $channels[] = new PrivateChannel('department.'.$this->ticket->department_id);
        }

        return $channels;
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->ticket_number,
            'parent_ticket_id' => $this->ticket->parent_ticket_id,
            'team_id' => $this->targetTeamId ?? $this->ticket->team_id,
            'status' => $this->ticket->status->value,
            'status_label' => $this->ticket->status->label(),
            'is_force_closed' => (bool) $this->ticket->is_force_closed,
            'closure_reason' => $this->ticket->closure_reason,
            'closed_by' => $this->ticket->closedBy ? "{$this->ticket->closedBy->first_name} {$this->ticket->closedBy->last_name}" : 'CWD Officer',
            'closed_at' => $this->ticket->closed_at?->format('M d, Y h:i A') ?? now()->format('M d, Y h:i A'),
            'department' => $this->ticket->department?->dept_name ?? 'Unassigned',
            'updated_at' => $this->ticket->updated_at?->format('M d, Y h:i A') ?? now()->format('M d, Y h:i A'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'TicketForceClosed';
    }
}
