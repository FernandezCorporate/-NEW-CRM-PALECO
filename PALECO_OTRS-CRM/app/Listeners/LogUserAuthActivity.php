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
                'role' => $event->user?->role?->slug_identifier,
            ])
            ->log($event->action_category->description());
    }
}
