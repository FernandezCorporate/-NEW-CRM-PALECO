<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Real-time event broadcast immediately after an admin creates, edits, archives, restores or deletes
 * a department or ticket category, so open forms and option lists know their choices changed.
 */
class ReferenceDataUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  string  $type  department or category
     * @param  string  $action  created, updated, archived, restored or deleted
     */
    public function __construct(
        public string $type,
        public string $action,
        public int $id,
        public ?string $name = null
    ) {}

    /**
     * Get the channels the event should broadcast on.
     * cwd.operations reaches CWD officers and every supervisor; a department change also reaches
     * that department's own channel.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('cwd.operations')];

        if ($this->type === 'department') {
            $channels[] = new PrivateChannel('department.'.$this->id);
        }

        return $channels;
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'type' => $this->type,
            'action' => $this->action,
            'id' => $this->id,
            'name' => $this->name,
            'updated_at' => now()->format('M d, Y h:i A'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'ReferenceDataUpdated';
    }
}
