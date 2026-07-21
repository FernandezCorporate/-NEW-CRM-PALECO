<?php

namespace App\Listeners;

use App\Events\LoginEvents;

use Illuminate\Contracts\Queue\ShouldQueue; // Flags the listener to be pushed to a background queue rather than running synchronously.

/*
 * Asynchronously processes dispatched LoginEvents to record authentication attempts.
 * Utilizes the Spatie Activitylog package to standardize and store the audit trail.
 * Extracts detailed user and request metadata from the event payload.
 */
class LogUserAuthActivity implements ShouldQueue
{
    /*
     * Executes the core logging logic when the listener is triggered.
     * Maps the event's NonModelActions enum directly to the respective Activitylog fields.
     */
    public function handle(LoginEvents $event): void
    {
        activity()
            ->useLog($event->action_category->value)
            ->event($event->action_category->event())
            ->causedBy($event->user)
            ->withProperties([
                "ip_address" => $event->ip_address,                                                               // Network origin of the login request.
                "user_agent" => $event->user_agent,                                                               // Browser or client details used for the request.
                "username"   => $event->user ? $event->user->username : $event->usernameInput,                    // Actual username, or the raw input if authentication fails.
                "full_name"  => $event->user ? ucwords(trim($event->user->first_name . ' ' . $event->user->last_name)) : null, // Formatted full name, if the user model exists.
                "role"       => $event->user?->role?->slug_identifier,                                            // The specific system role identifier tied to the user.
                "email"      => $event->user?->email,                                                             // The user's registered email address.
                "contact"    => $event->user?->contact                                                            // The user's registered contact number.
            ])
            ->log($event->action_category->description());
    }
}