<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Real-time event broadcast to one account's own channel when an admin deactivates, reactivates,
 * moves or resets it, so an open app signs out or resubscribes instead of waiting for its next request.
 */
class AccountUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  string  $change  deactivated, reactivated, department_changed or password_reset
     */
    public function __construct(
        public User $user,
        public string $change,
        public ?int $previousDepartmentId = null
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        return new PrivateChannel('user.'.$this->user->id);
    }

    /**
     * Get the data to broadcast. Nothing beyond what the account owner already has.
     */
    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->user->id,
            'change' => $this->change,
            'is_active' => (bool) $this->user->is_active,
            'department_id' => $this->user->department_id,
            'previous_department_id' => $this->previousDepartmentId,
            // Deactivation and password resets revoke the token, so the app must sign the person out
            'requires_sign_out' => in_array($this->change, ['deactivated', 'password_reset'], true),
            'updated_at' => now()->format('M d, Y h:i A'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'AccountUpdated';
    }
}
