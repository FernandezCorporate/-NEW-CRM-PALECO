<?php

namespace App\Livewire\Shared;

use App\Models\Ticket;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Reactive Livewire island that renders and updates the assigned department supervisors
 * and dispatched field crew roster in real time.
 */
class TicketAssignedPersonnel extends Component
{
    public Ticket $ticket;

    public function mount(Ticket $ticket): void
    {
        $this->ticket = $ticket;
    }

    #[On('echo-private:cwd.operations,.TicketAssigned')]
    #[On('echo-private:cwd.operations,.TicketVerifiedAndClosed')]
    #[On('echo-private:cwd.operations,.TicketForceClosed')]
    #[On('echo-private:cwd.operations,.TicketCancelled')]
    #[On('echo-private:cwd.operations,.TicketEndorsementDecided')]
    public function handleAssignmentChange($event = null): void
    {
        if ($event && isset($event['ticket_id']) && $event['ticket_id'] !== $this->ticket->id) {
            return;
        }

        $this->ticket->refresh();
        $this->ticket->load([
            'department.supervisors',
            'team.members',
        ]);
    }

    public function render()
    {
        return view('livewire.shared.ticket-assigned-personnel');
    }
}
