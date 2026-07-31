<?php

namespace App\Events;

use App\Enums\NonModelActions;
use App\Models\User;

use Illuminate\Foundation\Events\Dispatchable;  // Allows triggering the event fluently using LoginEvents::dispatch() instead of instantiation.
use Illuminate\Queue\SerializesModels;          // Gracefully shrinks Eloquent models to just their IDs when sent to background queues.

/*
 * Captures contextual data during system authentication attempts for auditing purposes.
 * Bundles request information with the specific login outcome defined by NonModelActions.
 * Serves as the payload/parameter for the handle() method on the LogUserAuthActivity listener, which generates activity log entries.
 */
class LoginEvents
{
    use Dispatchable, SerializesModels;

    public NonModelActions $action_category; // The specific authentication outcome defined by the NonModelActions enum (success, fail, deactivated).
    public ?User $user;                      // The user model attempting to log in, null if not found.
    public string $ip_address;               // The IP address where the login request originated.
    public string $user_agent;               // The browser or client used for the request.
    public ?string $usernameInput;           // The raw username string submitted during the attempt.

    /*
     * Initializes the event payload with the provided action category and user model.
     * Injects the value for the public properties declared above.
     * Automatically extracts the IP address, user agent, and username from the current HTTP request.
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