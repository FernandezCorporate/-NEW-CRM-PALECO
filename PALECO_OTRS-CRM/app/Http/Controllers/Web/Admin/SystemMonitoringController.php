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

        $logs = $this->activityLogService->getLogEntries(
            $request->only(['search', 'category', 'severity'])
        );

        return view('admin.pages.monitoring', compact('logs'));
    }
}
