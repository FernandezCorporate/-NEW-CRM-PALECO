<?php

namespace App\Events;

use App\Enums\NonModelActions;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LoginEvents
{
    use Dispatchable, SerializesModels;

    public NonModelActions $action_category;
    public ?User $user;
    public string $ip_address;
    public string $user_agent;
    public ?string $usernameInput;

    /**
     * Create a new event instance.
     */
    public function __construct(NonModelActions $action_category, ?User $user = null)
    {
        $this->action_category = $action_category;
        $this->user = $user;

        $this->ip_address = request()->ip() ?? 'unknown';
        $this->user_agent = request()->userAgent() ?? 'unknown';
        $this->usernameInput = request()->input('username');
    }
}
