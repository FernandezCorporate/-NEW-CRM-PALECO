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
 * Real-time event broadcast immediately upon the creation of a new ticket.
 */
class TicketCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public Ticket $ticket) {}

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
            'ticket_id' => $this->ticket->system_id,
            'ticket_number' => $this->ticket->ticket_number,
            'parent_ticket_id' => $this->ticket->parent_ticket_id,
            'status' => $this->ticket->status->value,
            'status_label' => $this->ticket->status->label(),
            'source' => $this->ticket->complaint_source->label(),
            'address' => implode(', ', array_filter([$this->ticket->purok, $this->ticket->street, $this->ticket->barangay])),
            'category' => $this->ticket->other_category ? $this->ticket->other_category_name : ($this->ticket->category?->category_name ?? 'Unspecified'),
            'department' => $this->ticket->department?->dept_name ?? 'Unassigned',
            'created_at' => $this->ticket->reported_at?->format('M d, Y') ?? now()->format('M d, Y'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'TicketCreated';
    }
}
