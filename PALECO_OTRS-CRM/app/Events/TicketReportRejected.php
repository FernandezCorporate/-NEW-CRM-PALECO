<?php

namespace App\Events;

use App\Models\Ticket;
use App\Models\TicketAccomplishment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Real-time event broadcast immediately after a supervisor rejects an accomplishment report,
 * sending the ticket back to In Progress with the reason the crew must address.
 */
class TicketReportRejected implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Ticket $ticket,
        public TicketAccomplishment $accomplishment
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('cwd.operations')];

        if ($this->ticket->team_id) {
            $channels[] = new PrivateChannel('team.'.$this->ticket->team_id);
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
            'status' => $this->ticket->status->value,
            'status_label' => $this->ticket->status->label(),
            'team_id' => $this->ticket->team_id,
            'team_name' => $this->ticket->team?->team_name ?? 'Field Team',
            'department_id' => $this->ticket->department_id,
            'department' => $this->ticket->department?->dept_name ?? 'Unassigned',
            'accomplishment_id' => $this->accomplishment->id,
            'rejection_reason' => $this->accomplishment->rejection_reason,
            'rejected_by' => $this->accomplishment->rejectedBy?->full_name ?? 'Supervisor',
            'updated_at' => $this->ticket->updated_at?->format('M d, Y h:i A') ?? now()->format('M d, Y h:i A'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'TicketReportRejected';
    }
}
