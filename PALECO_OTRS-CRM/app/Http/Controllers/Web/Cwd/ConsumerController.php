<?php

namespace App\Http\Controllers\Web\Cwd;

use App\Http\Controllers\Controller;
use App\Services\External\ConsumerService as ExternalConsumerService;
use App\Services\Web\Cwd\ConsumerService as WebConsumerService;
use Illuminate\Http\Request;
use App\Models\Consumer;
use Illuminate\Support\Facades\Gate;

class ConsumerController extends Controller
{
    public function verify(string $accountCode, ExternalConsumerService $service)
    {
        // Retrieves the array without touching the database
        $consumerData = $service->verifyAccount($accountCode);
        
        return response()->json([
            'success' => true,
            'consumer' => $consumerData
        ]);
    }

    public function index(Request $request, WebConsumerService $service)
    {
        Gate::authorize('viewAny', Consumer::class);
        $data = $service->getConsumerList($request);
        return view('cwd.pages.consumerManagement', $data);
    }

    public function show(Request $request, Consumer $consumer, WebConsumerService $service)
    {
        Gate::authorize('view', $consumer);
        $data = $service->getConsumerDetails($request, $consumer);
        return view('cwd.pages.consumerDetails', $data);
    }
}