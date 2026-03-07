<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PlanService;
use Illuminate\Http\JsonResponse;

class PlanController extends Controller
{
    public function __construct(
        protected PlanService $planService
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->planService->toArray(),
        ]);
    }
}
