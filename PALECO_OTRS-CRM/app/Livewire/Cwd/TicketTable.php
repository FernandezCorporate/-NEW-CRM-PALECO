<?php

namespace App\Livewire\Cwd;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Url;
use Livewire\Attributes\On;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Enums\TicketStatus;

class TicketTable extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';
    #[Url]
    public string $filter = 'all';
    #[Url]
    public string $status = 'all';
    #[Url]
    public string $sort = 'newest';

    // Listens to the exact event you configured in TicketCreated.php
    #[On('echo-private:cwd.operations,.TicketCreated')]
    public function handleNewTicket($event)
    {
        $this->resetPage();
    }

    public function render()
    {
        $tickets = Ticket::with(['category', 'department', 'consumer', 'parentTicket'])
            ->search($this->search)
            ->filterByCategory($this->filter)
            ->filterByStatus($this->status === 'all' ? null : $this->status)
            ->sort($this->sort)
            ->paginate(10);

        return view('livewire.cwd.ticket-table', [
            'tickets' => $tickets,
            'categories' => TicketCategory::orderBy('category_name')->get(),
            'statuses' => TicketStatus::cases(),
        ]);
    }
}