<?php

namespace App\Services\Web\Admin;

use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\Activitylog\Models\Activity;

/*
 * Manages the retrieval, filtering, and presentation formatting of system audit activity logs.
 */
class ActivityLogService
{
    // --- QUERY & AGGREGATION METHODS ---

    /*
     * Retrieves a paginated list of system activity logs filtered by search terms and category tags.
     */
    public function getLogEntries(array $filters): LengthAwarePaginator
    {
        $query = Activity::with(['causer.role'])->latest();

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', $search)
                    ->orWhere('log_name', 'like', $search)
                    ->orWhere('event', 'like', $search);
            });
        }

        if (! empty($filters['category']) && $filters['category'] !== 'All Categories') {
            $query->where('log_name', $filters['category']);
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
