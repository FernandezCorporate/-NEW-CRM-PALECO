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
 * Real-time event broadcast immediately after a CWD officer edits an Open ticket's intake details,
 * including re-routing it to another department.
 */
class TicketIntakeUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Ticket $ticket,
        public ?int $previousDepartmentId = null
    ) {}

    /**
     * Get the channels the event should broadcast on.
     * A re-routed ticket reaches both the department that lost it and the one that gained it.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('cwd.operations')];

        if ($this->ticket->department_id) {
            $channels[] = new PrivateChannel('department.'.$this->ticket->department_id);
        }

        if ($this->departmentChanged()) {
            $channels[] = new PrivateChannel('department.'.$this->previousDepartmentId);
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
            'department_id' => $this->ticket->department_id,
            'department' => $this->ticket->department?->dept_name ?? 'Unassigned',
            'previous_department_id' => $this->previousDepartmentId,
            'department_changed' => $this->departmentChanged(),
            'updated_at' => $this->ticket->updated_at?->format('M d, Y h:i A') ?? now()->format('M d, Y h:i A'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'TicketIntakeUpdated';
    }

    private function departmentChanged(): bool
    {
        return $this->previousDepartmentId !== null
            && $this->previousDepartmentId !== (int) $this->ticket->department_id;
    }
}
