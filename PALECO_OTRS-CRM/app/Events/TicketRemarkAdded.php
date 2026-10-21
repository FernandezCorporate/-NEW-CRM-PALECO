<?php

namespace App\Events;

use App\Models\TicketRemark;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Real-time event broadcast immediately after a remark is posted on a ticket.
 * Internal remarks only reach CWD operations and never carry their text.
 */
class TicketRemarkAdded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public TicketRemark $remark) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $ticket = $this->remark->ticket;
        $channels = [new PrivateChannel('cwd.operations')];

        // Mobile users never see internal remarks, so only public ones reach the crew and department
        if (! $this->remark->is_internal && $ticket) {
            if ($ticket->team_id) {
                $channels[] = new PrivateChannel('team.'.$ticket->team_id);
            }

            if ($ticket->department_id) {
                $channels[] = new PrivateChannel('department.'.$ticket->department_id);
            }
        }

        return $channels;
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        $ticket = $this->remark->ticket;

        return [
            'ticket_id' => $this->remark->ticket_id,
            'ticket_number' => $ticket?->ticket_number,
            'parent_ticket_id' => $ticket?->parent_ticket_id,
            'team_id' => $ticket?->team_id,
            'department_id' => $ticket?->department_id,
            'remark_id' => $this->remark->id,
            'is_internal' => (bool) $this->remark->is_internal,
            // cwd.operations also reaches supervisors, so the text of an internal note is never broadcast
            'body' => $this->remark->is_internal ? null : $this->remark->body,
            'author' => $this->remark->author?->full_name ?? 'System',
            'author_role' => $this->remark->author?->role?->role_name,
            'created_at' => $this->remark->created_at?->format('M d, Y h:i A') ?? now()->format('M d, Y h:i A'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'TicketRemarkAdded';
    }
}
