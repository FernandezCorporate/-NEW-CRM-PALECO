<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Services\Web\Admin\ActivityLogService;
use Illuminate\Http\Request;
use App\Policies\ActivityPolicy;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;

class SystemMonitoringController extends Controller
{
    public function __construct(protected ActivityLogService $activityLogService) {}

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Activity::class);

        // 1. Pass the search, category, and severity inputs to the service
        $logs = $this->activityLogService->getLogEntries(
            $request->only(['search', 'category', 'severity'])
        );

        // 2. Return the data to your Blade frontend
        return view('admin.pages.monitoring', compact('logs'));
    }
}