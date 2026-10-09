<?php

namespace App\Services\Consumers;

use App\Enums\ComplaintSources;
use App\Enums\TicketStatus;
use App\Models\Consumer;
use Illuminate\Http\Request;

/**
 * Manages utility consumer search, ticket history aggregation, and account metric computation.
 */
class ConsumerService
{
    // --- QUERY METHODS ---

    /**
     * Retrieves a paginated list of consumers with count of currently active service tickets.
     */
    public function getConsumerList(Request $request): array
    {
        $consumers = Consumer::query()
            ->withCount(['tickets as open_tickets_count' => function ($query) {
                $query->whereIn('status', [TicketStatus::OPEN, TicketStatus::ASSIGNED, TicketStatus::IN_PROGRESS]);
            }])
            ->search($request->search)
            ->paginate(12);

        return compact('consumers');
    }

    /**
     * Compiles detailed consumer profile data, historical complaints, and communication source statistics.
     */
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
            ->selectRaw('complaint_source, COUNT(*) as total')
            ->groupBy('complaint_source')
            ->pluck('total', 'complaint_source');

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
