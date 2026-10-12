<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogs\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

/*
 * Manages the display and filtering of system activity audit logs for administrators.
 */
class SystemMonitoringController extends Controller
{
    public function __construct(
        protected ActivityLogService $activityLogService
    ) {}

    // --- VIEW METHODS ---

    /*
     * Retrieves and displays the system activity and audit trail log entries.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Activity::class);

        session()->put('monitoring_list_url', $request->fullUrl());

        $dashboardData = $this->activityLogService->getMonitoringDashboard(
            $request->only(['search', 'category', 'event_type'])
        );

        return view('admin.pages.monitoring', $dashboardData);
    }

    /*
     * Displays granular details, forensic context, and attribute diffs for a single audit log entry.
     */
    public function show(Activity $activity): View
    {
        Gate::authorize('view', $activity);

        $detailData = $this->activityLogService->getActivityDetail($activity);

        return view('admin.pages.activityLogDetails', $detailData);
    }
}
