<?php

namespace App\Services\ActivityLogs;

use App\Enums\NonModelActions;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\Activitylog\Models\Activity;

/**
 * Manages the retrieval, filtering, and presentation formatting of system audit activity logs.
 */
class ActivityLogService
{
    // --- QUERY & AGGREGATION METHODS ---

    /**
     * Retrieves dashboard data containing paginated activity logs and server-provided dropdown options.
     */
    public function getMonitoringDashboard(array $filters): array
    {
        return [
            'logs' => $this->getLogEntries($filters),
            'categories' => $this->getCategoryOptions(),
            'eventTypes' => $this->getEventTypeOptions(),
        ];
    }

    /**
     * Returns strictly defined category filter options for the system monitoring dashboard.
     */
    public function getCategoryOptions(): array
    {
        return [
            'all' => 'All Categories',
            'auth' => 'Authentication',
            'Tickets' => 'Tickets',
            'Users' => 'Users',
            'Teams' => 'Teams',
            'Department' => 'Departments',
            'TicketCategory' => 'Ticket Categories',
        ];
    }

    /**
     * Returns strictly defined event type filter options.
     */
    public function getEventTypeOptions(): array
    {
        return [
            'all' => 'All Events',
            'create' => 'Create',
            'update' => 'Update',
            'delete' => 'Delete',
            'other' => 'Other',
        ];
    }

    /**
     * Retrieves a paginated list of system activity logs filtered by search terms, category, and action event type.
     */
    public function getLogEntries(array $filters): LengthAwarePaginator
    {
        $query = Activity::with(['causer.role', 'subject'])->latest();

        $nonModelLogNames = array_map(fn ($case) => $case->value, NonModelActions::cases());
        $crudEvents = ['created', 'create', 'updated', 'update', 'deleted', 'delete', 'restored', 'restore'];

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', $search)
                    ->orWhere('log_name', 'like', $search)
                    ->orWhere('event', 'like', $search)
                    ->orWhere('properties', 'like', $search);
            });
        }

        $category = $filters['category'] ?? null;
        if (! empty($category) && ! in_array($category, ['all', 'All Categories'], true)) {
            if (in_array($category, ['auth', 'login_success', 'login', 'authentication'], true)) {
                $query->where(function ($q) use ($nonModelLogNames) {
                    $q->whereIn('log_name', $nonModelLogNames)
                        ->orWhere('event', 'login');
                });
            } else {
                $query->where('log_name', $category);
            }
        }

        $eventType = $filters['event_type'] ?? $filters['action'] ?? $filters['event'] ?? null;
        if (! empty($eventType) && $eventType !== 'all') {
            if ($eventType === 'create' || $eventType === 'created') {
                $query->whereIn('event', ['created', 'create'])
                    ->whereNotIn('log_name', $nonModelLogNames);
            } elseif ($eventType === 'update' || $eventType === 'updated') {
                $query->whereIn('event', ['updated', 'update'])
                    ->whereNotIn('log_name', $nonModelLogNames);
            } elseif ($eventType === 'delete' || $eventType === 'deleted') {
                $query->whereIn('event', ['deleted', 'delete'])
                    ->whereNotIn('log_name', $nonModelLogNames);
            } elseif ($eventType === 'other' || $eventType === 'non_crud') {
                $query->where(function ($q) use ($nonModelLogNames, $crudEvents) {
                    $q->whereIn('log_name', $nonModelLogNames)
                        ->orWhere('event', 'login')
                        ->orWhereNotIn('event', $crudEvents);
                });
            } elseif ($eventType === 'crud') {
                $query->whereIn('event', ['created', 'updated', 'deleted', 'restored'])
                    ->whereNotIn('log_name', $nonModelLogNames);
            }
        }

        $paginator = $query->paginate(15)->withQueryString();

        $paginator->getCollection()->transform(function ($log) {
            $log->formatted_date = $log->created_at->format('M d, Y');
            $log->formatted_time = $log->created_at->format('h:i A');
            $log->formatted_datetime = $log->created_at->format('M d, Y h:i A');

            return $log;
        });

        return $paginator;
    }
}
