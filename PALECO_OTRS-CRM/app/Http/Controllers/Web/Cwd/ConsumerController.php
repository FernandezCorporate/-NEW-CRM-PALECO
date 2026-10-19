<?php

namespace App\Http\Controllers\Web\Cwd;

use App\Http\Controllers\Controller;
use App\Models\Consumer;
use App\Services\Consumers\ConsumerService;
use App\Services\External\PalecoBillingApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/*
 * Manages the retrieval, live verification, and customer profile views for utility consumers.
 */
class ConsumerController extends Controller
{
    public function __construct(
        protected ConsumerService $consumerService,
        protected PalecoBillingApiService $palecoBillingApiService
    ) {}

    // --- VIEW METHODS ---

    /*
     * Renders the paginated list of consumers with linked ticket counts.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Consumer::class);

        session()->put('consumer_list_url', $request->fullUrl());

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
            $consumerData = $this->palecoBillingApiService->verifyAccount($accountCode);

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

    /*
     * Performs a multi-criteria search against the external billing API (restricted to Puerto Princesa: paleco_db).
     * Supports searching by last name (or Lastname,Firstname) or by meter serial number.
     */
    public function search(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'lastname' => ['required_without:meter_serial', 'nullable', 'string', 'min:2', 'max:100'],
            'meter_serial' => ['required_without:lastname', 'nullable', 'string', 'min:2', 'max:100'],
        ], [
            'lastname.required_without' => 'Please provide a last name to search.',
            'meter_serial.required_without' => 'Please provide a meter serial number to search.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first('lastname') ?: ($validator->errors()->first('meter_serial') ?: 'Invalid search parameters.'),
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $validated = $validator->validated();
            $results = $this->palecoBillingApiService->searchAccounts(
                $validated['lastname'] ?? null,
                $validated['meter_serial'] ?? null,
                30
            );

            return response()->json([
                'success' => true,
                'data' => $results['data'],
                'has_more' => $results['has_more'],
                'count' => count($results['data']),
                'source' => $results['source'] ?? 'api',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to search consumer accounts with billing service.',
            ], 500);
        }
    }
}
