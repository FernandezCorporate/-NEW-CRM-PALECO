<?php

namespace App\Livewire\Cwd;

use Livewire\Component;
use Livewire\Attributes\On;
use App\Services\Web\Dashboard\DashboardService;

class DashboardOverview extends Component
{
    public array $overview;

    public function mount(DashboardService $service)
    {
        $this->overview = $service->ticketOverview();
    }

    // FIXED: Added the '.' before TicketCreated so Echo resolves the App\Events namespace
    #[On('echo-private:cwd.operations,.TicketCreated')]
    public function refreshMetrics(DashboardService $service)
    {
        $this->overview = $service->ticketOverview();
    }

    public function render()
    {
        return view('livewire.cwd.dashboard-overview');
    }
}