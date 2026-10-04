<?php

namespace App\Services\Web\Cwd;

use Illuminate\Http\Request;
use App\Enums\TicketStatus;
use App\Models\Consumer;
use App\Enums\ComplaintSources;

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

    public function getConsumerDetails(Request $request, Consumer $consumer): array
    {
        $consumer->loadCount(['tickets as open_tickets_count' => function ($query) {
            $query->whereIn('status', [TicketStatus::OPEN, TicketStatus::ASSIGNED, TicketStatus::IN_PROGRESS]);
        }]);

        $lastKnownContact = $consumer->tickets()
            ->whereNotNull('consumer_contact')
            ->latest('reported_at')
            ->value('consumer_contact');

        $rawSourceMetrics = $consumer->tickets()
            ->pluck('complaint_source')
            ->countBy();

        $sourceMetrics = [];
        foreach (ComplaintSources::cases() as $source) {
            $sourceMetrics[$source->value] = $rawSourceMetrics->get($source->value, 0);
        }

        $historicalTickets = $consumer->tickets()
            ->with(['category', 'department'])
            ->latest('reported_at')
            ->paginate(10);

        return [
            'consumer' => $consumer,
            'lastKnownContact' => $lastKnownContact,
            'sourceMetrics' => $sourceMetrics,
            'historicalTickets' => $historicalTickets,
        ];
    }
}