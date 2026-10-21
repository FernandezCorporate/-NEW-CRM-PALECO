<?php

namespace App\Events;

use App\Models\Team;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Real-time event broadcast immediately after a team is created, edited, archived, restored or deleted,
 * so rosters stay current and crews added or removed can join or leave the team's channel.
 */
class TeamUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  string  $action  created, updated, archived, restored or deleted
     * @param  array<int, string>  $addedMemberIds
     * @param  array<int, string>  $removedMemberIds
     * @param  array<int, string>  $notifiedMemberIds  members told on their own channel without joining or leaving (archive, restore)
     */
    public function __construct(
        public Team $team,
        public string $action,
        public array $addedMemberIds = [],
        public array $removedMemberIds = [],
        public array $notifiedMemberIds = [],
        public ?int $previousDepartmentId = null
    ) {}

    /**
     * Get the channels the event should broadcast on.
     * Added and removed members also hear it on their own user channel: an added member is not on the
     * team channel yet, and a removed member keeps that subscription until their app leaves it.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('cwd.operations'),
            new PrivateChannel('team.'.$this->team->id),
        ];

        if ($this->team->department_id) {
            $channels[] = new PrivateChannel('department.'.$this->team->department_id);
        }

        if ($this->previousDepartmentId && $this->previousDepartmentId !== (int) $this->team->department_id) {
            $channels[] = new PrivateChannel('department.'.$this->previousDepartmentId);
        }

        $userIds = array_unique(array_merge($this->addedMemberIds, $this->removedMemberIds, $this->notifiedMemberIds));
        foreach ($userIds as $userId) {
            $channels[] = new PrivateChannel('user.'.$userId);
        }

        return $channels;
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'team_id' => $this->team->id,
            'team_name' => $this->team->team_name,
            'department_id' => $this->team->department_id,
            'previous_department_id' => $this->previousDepartmentId,
            'action' => $this->action,
            'added_member_ids' => array_values($this->addedMemberIds),
            'removed_member_ids' => array_values($this->removedMemberIds),
            'is_archived' => in_array($this->action, ['archived', 'deleted'], true),
            'updated_at' => now()->format('M d, Y h:i A'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'TeamUpdated';
    }
}
