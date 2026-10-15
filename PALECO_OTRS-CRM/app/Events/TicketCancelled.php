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
 * Real-time event broadcast immediately upon the cancellation of an open ticket.
 */
class TicketCancelled implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public Ticket $ticket) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
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
            'cancellation_reason' => $this->ticket->cancellation_reason,
            'cancelled_at' => $this->ticket->cancelled_at?->format('M d, Y h:i A') ?? now()->format('M d, Y h:i A'),
            'department' => $this->ticket->department?->dept_name ?? 'Unassigned',
            'updated_at' => $this->ticket->updated_at?->format('M d, Y h:i A') ?? now()->format('M d, Y h:i A'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'TicketCancelled';
    }
}

