<?php

namespace App\Livewire\Shared;

use App\Models\Ticket;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Reactive Livewire island managing real-time activity and service history tables
 * (Status changes, Team assignments, Endorsements, Field reports) and their tab counters.
 * Note: Activity remarks thread is intentionally excluded from real-time WebSockets.
 */
class TicketHistoryTabs extends Component
{
    public Ticket $ticket;

    public bool $isAdmin = false;

    public ?string $source = null;

    public ?string $consumer = null;

    public ?string $parent = null;

    public function mount(Ticket $ticket, bool $isAdmin = false, ?string $source = null, ?string $consumer = null, ?string $parent = null): void
    {
        $this->ticket = $ticket;
        $this->isAdmin = $isAdmin;
        $this->source = $source ?? request('source');
        $this->consumer = $consumer ?? request('consumer');
        $this->parent = $parent ?? request('parent');
    }

    #[On('echo-private:cwd.operations,.TicketAssigned')]
    #[On('echo-private:cwd.operations,.TicketWorkStarted')]
    #[On('echo-private:cwd.operations,.TicketAccomplished')]
    #[On('echo-private:cwd.operations,.TicketVerifiedAndClosed')]
    #[On('echo-private:cwd.operations,.TicketCancelled')]
    #[On('echo-private:cwd.operations,.TicketForceClosed')]
    #[On('echo-private:cwd.operations,.TicketEndorsementInitiated')]
    #[On('echo-private:cwd.operations,.TicketEndorsementDecided')]
    #[On('echo-private:cwd.operations,.TicketIntakeUpdated')]
    #[On('echo-private:cwd.operations,.TicketReportRejected')]
    #[On('echo-private:cwd.operations,.TicketRemarkAdded')]
    public function handleStatusChange($event = null): void
    {
        if ($event && isset($event['ticket_id']) && $event['ticket_id'] !== $this->ticket->id) {
            return;
        }

        $this->ticket->refresh();
        $this->ticket->load([
            'statusLog.updater',
            'assignments.team',
            'assignments.assigner',
            'endorsements.suggestedDepartment',
            'endorsements.oldDepartment',
            'endorsements.newDepartment',
            'endorsements.creator',
            'endorsements.reviewer',
            'accomplishments.accomplishedBy',
            'accomplishments.approvedBy',
            'accomplishments.rejectedBy',
            'remarks.author.role',
        ]);
    }

    public function render()
    {
        return view('livewire.shared.ticket-history-tabs');
    }
}
