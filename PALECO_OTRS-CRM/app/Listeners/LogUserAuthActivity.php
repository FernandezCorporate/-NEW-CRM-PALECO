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
                "ip_address" => $event->ip_address,                                                               
                "user_agent" => $event->user_agent,                                                               
                "username"   => $event->user ? $event->user->username : $event->usernameInput,                    
                "full_name"  => $event->user ? ucwords(trim($event->user->first_name . ' ' . $event->user->last_name)) : null, 
                "role"       => $event->user?->role?->slug_identifier,                                            
                "email"      => $event->user?->email,                                                             
                "contact"    => $event->user?->contact                                                            
            ])
            ->log($event->action_category->description());
    }
}