<?php

namespace App\Livewire\Shared;

use App\Models\Ticket;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Reactive Livewire island that renders the ticket hierarchy tree (origin, current, and child tickets)
 * and keeps status pills synchronized in real time via WebSockets.
 */
class TicketHierarchy extends Component
{
    public Ticket $ticket;
    public bool $isAdmin = false;
    public ?string $source = null;
    public ?string $consumer = null;

    public function mount(Ticket $ticket, bool $isAdmin = false, ?string $source = null, ?string $consumer = null): void
    {
        $this->ticket = $ticket;
        $this->isAdmin = $isAdmin;
        $this->source = $source ?? request('source');
        $this->consumer = $consumer ?? request('consumer');
    }

    #[On('echo-private:cwd.operations,.TicketCreated')]
    #[On('echo-private:cwd.operations,.TicketAssigned')]
    #[On('echo-private:cwd.operations,.TicketWorkStarted')]
    #[On('echo-private:cwd.operations,.TicketAccomplished')]
    #[On('echo-private:cwd.operations,.TicketVerifiedAndClosed')]
    #[On('echo-private:cwd.operations,.TicketCancelled')]
    #[On('echo-private:cwd.operations,.TicketForceClosed')]
    #[On('echo-private:cwd.operations,.TicketEndorsementInitiated')]
    #[On('echo-private:cwd.operations,.TicketEndorsementDecided')]
    public function handleHierarchyUpdate($event = null): void
    {
        if ($event && isset($event['ticket_id'])) {
            $eventId = $event['ticket_id'];
            $eventParentId = $event['parent_ticket_id'] ?? null;

            $isSelf = $eventId === $this->ticket->id;
            $isParent = $this->ticket->parent_ticket_id && $eventId === $this->ticket->parent_ticket_id;
            $isChild = $this->ticket->childTickets->contains('id', $eventId);
            $isNewChildOfCurrent = $eventParentId && $eventParentId === $this->ticket->id;

            if (! $isSelf && ! $isParent && ! $isChild && ! $isNewChildOfCurrent) {
                return;
            }
        }

        $this->ticket->refresh();
        $this->ticket->load([
            'department',
            'parentTicket.department',
            'childTickets.department',
        ]);
    }

    public function render()
    {
        return view('livewire.shared.ticket-hierarchy');
    }
}

