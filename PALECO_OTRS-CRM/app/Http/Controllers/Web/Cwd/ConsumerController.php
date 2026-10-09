<?php

namespace App\Http\Controllers\Web\Cwd;

use App\Http\Controllers\Controller;
use App\Models\Consumer;
use App\Services\Consumers\ConsumerService as WebConsumerService;
use App\Services\External\ConsumerService as ExternalConsumerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/*
 * Manages the retrieval, live verification, and customer profile views for utility consumers.
 */
class ConsumerController extends Controller
{
    public function __construct(
        protected WebConsumerService $consumerService,
        protected ExternalConsumerService $externalConsumerService
    ) {}

    // --- VIEW METHODS ---

    /*
     * Renders the paginated list of consumers with linked ticket counts.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Consumer::class);

        $data = $this->consumerService->getConsumerList($request);

        return view('cwd.pages.consumerManagement', $data);
    }

    /*
     * Renders detailed historical complaint metrics and account profile for a consumer.
     */
    public function show(Request $request, Consumer $consumer): View
    {
        Gate::authorize('view', $consumer);

        $data = $this->consumerService->getConsumerDetails($request, $consumer);

        return view('cwd.pages.consumerDetails', $data);
    }

    // --- AJAX / VERIFICATION METHODS ---

    /*
     * Performs a live lookup against the external billing API for account validation without persisting to DB.
     */
    public function verify(string $accountCode): JsonResponse
    {
        try {
            $consumerData = $this->externalConsumerService->verifyAccount($accountCode);

            return response()->json([
                'success' => true,
                'consumer' => $consumerData,
                'data' => $consumerData,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->validator->errors()->first('account_code') ?? $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to verify account code with billing service.',
            ], 500);
        }
    }
}
