<?php

namespace App\Http\Controllers\Web\Remarks;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Remarks\StoreTicketRemarkRequest;
use App\Models\Ticket;
use App\Models\TicketRemark;
use App\Services\Web\Remarks\TicketRemarkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class TicketRemarkController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected TicketRemarkService $remarkService
    ) {}

    public function store(StoreTicketRemarkRequest $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('create', [TicketRemark::class, $ticket]);

        $this->remarkService->createRemark(
            $ticket,
            $request->user(),
            $request->validated()
        );

        return back()->with('success', 'Remark added successfully.');
    }
}