<?php

namespace App\Livewire\Cwd;

use App\Services\Dashboard\WebDashboardService;
use Livewire\Attributes\On;
use Livewire\Component;

class DashboardOverview extends Component
{
    public array $overview;

    public function mount(WebDashboardService $service)
    {
        $this->overview = $service->ticketOverview();
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
    public function refreshMetrics(WebDashboardService $service)
    {
        $this->overview = $service->ticketOverview();
    }

    public function render()
    {
        return view('livewire.cwd.dashboard-overview');
    }
}
