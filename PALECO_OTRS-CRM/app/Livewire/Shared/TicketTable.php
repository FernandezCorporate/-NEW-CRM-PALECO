<?php

namespace App\Livewire\Shared;

use App\Enums\TicketStatus;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketCategory;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * Shared Livewire table component for real-time ticket searching, filtering, and pagination.
 * Shared across CWD Officers and Administrators.
 */
class TicketTable extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filter = 'all';

    #[Url]
    public string $department = 'all';

    #[Url]
    public string $status = 'all';

    #[Url]
    public string $sort = 'newest';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDepartment(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    #[On('echo-private:cwd.operations,.TicketCreated')]
    public function handleNewTicket($event)
    {
        $this->resetPage();
    }

    #[On('echo-private:cwd.operations,.TicketAssigned')]
    #[On('echo-private:cwd.operations,.TicketWorkStarted')]
    #[On('echo-private:cwd.operations,.TicketAccomplished')]
    #[On('echo-private:cwd.operations,.TicketVerifiedAndClosed')]
    public function handleStatusUpdated($event = null): void
    {
        // Trigger component re-render so status badges and assigned teams update in real time
    }

    public function render()
    {
        $tickets = Ticket::with(['category', 'department', 'consumer', 'parentTicket'])
            ->search($this->search)
            ->filterByCategory($this->filter)
            ->filterByDepartment($this->department)
            ->filterByStatus($this->status === 'all' ? null : $this->status)
            ->sort($this->sort)
            ->paginate(10);

        return view('livewire.shared.ticket-table', [
            'tickets' => $tickets,
            'categories' => TicketCategory::orderBy('category_name')->get(),
            'departments' => Department::orderBy('dept_name')->get(),
            'statuses' => TicketStatus::cases(),
            'isAdmin' => auth()->user()?->role?->slug_identifier === 'admin',
        ]);
    }
}

