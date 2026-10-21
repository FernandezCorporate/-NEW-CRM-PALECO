<?php

namespace App\Livewire\Shared;

use App\Models\Ticket;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Reactive Livewire island that renders and updates the ticket's lifecycle status badge in real time.
 */
class TicketStatusBadge extends Component
{
    public Ticket $ticket;

    public function mount(Ticket $ticket): void
    {
        $this->ticket = $ticket;
    }

    #[On('echo-private:cwd.operations,.TicketAssigned')]
    #[On('echo-private:cwd.operations,.TicketWorkStarted')]
    #[On('echo-private:cwd.operations,.TicketAccomplished')]
    #[On('echo-private:cwd.operations,.TicketVerifiedAndClosed')]
    #[On('echo-private:cwd.operations,.TicketCancelled')]
    #[On('echo-private:cwd.operations,.TicketForceClosed')]
    #[On('echo-private:cwd.operations,.TicketEndorsementInitiated')]
    #[On('echo-private:cwd.operations,.TicketEndorsementDecided')]
    public function handleStatusChange($event = null): void
    {
        if ($event && isset($event['ticket_id']) && $event['ticket_id'] !== $this->ticket->id) {
            return;
        }

        $this->ticket->refresh();
    }

    public function render()
    {
        return view('livewire.shared.ticket-status-badge');
    }
}
