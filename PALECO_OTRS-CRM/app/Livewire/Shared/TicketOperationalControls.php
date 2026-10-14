<?php

namespace App\Livewire\Shared;

use App\Models\Ticket;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Reactive Livewire island managing real-time visibility of ticket operational controls
 * (Edit, Create Child, Cancel, Force Close) and terminal status notices (Cancelled, Force Closed).
 */
class TicketOperationalControls extends Component
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
    public function handleStatusChange($event = null): void
    {
        if ($event && isset($event['ticket_id']) && $event['ticket_id'] !== $this->ticket->id) {
            return;
        }

        $this->ticket->refresh();
        $this->ticket->load(['closedBy']);
    }

    public function render()
    {
        return view('livewire.shared.ticket-operational-controls');
    }
}

