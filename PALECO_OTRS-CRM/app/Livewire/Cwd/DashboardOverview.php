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

    // FIXED: Added the '.' before TicketCreated so Echo resolves the App\Events namespace
    #[On('echo-private:cwd.operations,.TicketCreated')]
    public function refreshMetrics(WebDashboardService $service)
    {
        $this->overview = $service->ticketOverview();
    }

    public function render()
    {
        return view('livewire.cwd.dashboard-overview');
    }
}
