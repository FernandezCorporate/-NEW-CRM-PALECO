<?php

namespace App\Services\Web\Cwd;

use Illuminate\Http\Request;
use App\Enums\TicketStatus;
use App\Models\Consumer;

class ConsumerService
{
    public function getConsumerList(Request $request)
    {
        $consumers = Consumer::query()
            ->withCount(['tickets as open_tickets_count' => function ($query) {
                $query->whereNotIn('status', [TicketStatus::RESOLVED, TicketStatus::CLOSED]);
            }])
            ->search($request->search)
            ->paginate(12);

        return compact('consumers');
    }
}