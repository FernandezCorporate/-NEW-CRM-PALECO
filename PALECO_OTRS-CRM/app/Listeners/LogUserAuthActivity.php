<?php

namespace App\Listeners;

use App\Events\LoginEvents;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Asynchronously processes dispatched LoginEvents to record authentication attempts.
 * Utilizes the Spatie Activitylog package to store standardized audit entries.
 */
class LogUserAuthActivity implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(LoginEvents $event): void
    {
        activity()
            ->useLog($event->action_category->value)
            ->event($event->action_category->event())
            ->causedBy($event->user)
            ->withProperties([
                'ip_address' => $event->ip_address,
                'user_agent' => $event->user_agent,
                'username' => $event->user ? $event->user->username : $event->usernameInput,
                'full_name' => $event->user ? ucwords(trim($event->user->first_name.' '.$event->user->last_name)) : null,
                'role' => $event->user?->role?->slug_identifier,
                'email' => $event->user?->email,
                'contact' => $event->user?->contact,
            ])
            ->log($event->action_category->description());
    }
}
