<?php

namespace App\Services\Web\Admin;

use Spatie\Activitylog\Models\Activity;
use Illuminate\Pagination\LengthAwarePaginator;

class ActivityLogService
{
    /**
     * Retrieves a paginated list of system activity logs.
     * Eager loads the causer and their role to minimize N+1 queries on the frontend.
     */
    public function getLogEntries(array $filters): LengthAwarePaginator
    {
        $query = Activity::with(['causer.role'])->latest();

        // 1. Search Filter: Matches against the primary action text or category
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', $search)
                  ->orWhere('log_name', 'like', $search)
                  ->orWhere('event', 'like', $search);
            });
        }

        // 2. Category Filter: Matches the specific log_name (e.g., 'Tickets', 'Authentication')
        if (!empty($filters['category']) && $filters['category'] !== 'All Categories') {
            $query->where('log_name', $filters['category']);
        }

        $paginator = $query->paginate(15)->withQueryString();

        // 3. Apply Date Formatting at the Service Layer
        $paginator->getCollection()->transform(function ($log) {
            $log->formatted_date = $log->created_at->format('M d, Y');
            $log->formatted_time = $log->created_at->format('h:i A');
            $log->formatted_datetime = $log->created_at->format('M d, Y h:i A');
            return $log;
        });

        return $paginator;
    }
}