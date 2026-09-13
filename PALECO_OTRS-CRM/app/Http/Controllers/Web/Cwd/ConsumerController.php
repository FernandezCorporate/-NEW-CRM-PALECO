<?php

namespace App\Http\Controllers\Web\Cwd;

use App\Http\Controllers\Controller;
use App\Services\External\ConsumerService;

class ConsumerController extends Controller
{
    public function verify(string $accountCode, ConsumerService $service)
    {
        // Retrieves the array without touching the database
        $consumerData = $service->verifyAccount($accountCode);
        
        return response()->json([
            'success' => true,
            'consumer' => $consumerData
        ]);
    }
}