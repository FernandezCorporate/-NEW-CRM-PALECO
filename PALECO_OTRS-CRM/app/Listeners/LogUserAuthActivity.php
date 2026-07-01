<?php

namespace App\Listeners;

use App\Events\LoginEvents;
use Illuminate\Contracts\Queue\ShouldQueue;

class LogUserAuthActivity implements ShouldQueue
{

    public function handle(LoginEvents $event): void
    {
        if ($event->action_category->value === 'login_success' && $event->user) {
            $event->user->updateQuietly(['last_login' => now()]);
        }

        activity()
            ->useLog($event->action_category->value)
            ->event($event->action_category->event())
            ->causedBy($event->user)
            ->withProperties([
                "ip_address" => $event->ip_address,
                "user_agent" => $event->user_agent,
                "username"   => $event->user ? $event->user->username : $event->usernameInput,
                "full_name"  => $event->user ? ucwords(trim($event->user->first_name . ' ' . $event->user->last_name)) : null,
                "role"       => $event->user?->role?->value,
                "email"      => $event->user?->email,
                "contact"    => $event->user?->contact
            ])
            ->log($event->action_category->description());
    }
}
