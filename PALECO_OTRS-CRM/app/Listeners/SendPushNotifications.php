<?php

namespace App\Listeners;

use App\Events\TeamUpdated;
use App\Events\TicketAccomplished;
use App\Events\TicketAssigned;
use App\Events\TicketCancelled;
use App\Events\TicketCreated;
use App\Events\TicketEndorsementDecided;
use App\Events\TicketForceClosed;
use App\Events\TicketRemarkAdded;
use App\Events\TicketReportRejected;
use App\Events\TicketVerifiedAndClosed;
use App\Services\Push\PushGateway;
use App\Services\Push\PushMessageFactory;

use function Illuminate\Support\defer;

/**
 * Sends a push notification for each ticket event that someone on the mobile app should act on.
 * It runs beside the real-time broadcast of the same event: Pusher updates open screens, push reaches closed apps.
 */
class SendPushNotifications
{
    public function __construct(
        private PushMessageFactory $factory,
        private PushGateway $gateway,
    ) {}

    public function handle(
        TicketCreated|TicketAccomplished|TicketEndorsementDecided|TicketAssigned|TicketCancelled|TicketForceClosed|TicketReportRejected|TicketVerifiedAndClosed|TicketRemarkAdded|TeamUpdated $event
    ): void {
        // Recipients are worked out now, while the request still knows who acted
        $messages = rescue(fn () => $this->factory->forEvent($event, auth()->id()), [], report: true);

        if (! $messages) {
            return;
        }

        // Sent after the response, so the user's action isn't slowed down; a delivery problem is logged, never shown to the user
        defer(fn () => rescue(fn () => $this->gateway->send($messages), report: true));
    }
}
