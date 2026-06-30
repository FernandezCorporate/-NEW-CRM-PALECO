<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Auth\Events\Login;

class UpdateLastLoginTimestamp
{
    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        $event->user->updateQuietly([
            'last_login' => now(),
        ]);
    }
}
