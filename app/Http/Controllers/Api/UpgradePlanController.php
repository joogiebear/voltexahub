<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UpgradePlan;
use Illuminate\Http\JsonResponse;

class UpgradePlanController extends Controller
{
    public function index(): JsonResponse
    {
        $plans = UpgradePlan::with('requiredPlan:id,name,color')
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('price')
            ->get();

        return response()->json(['data' => $plans]);
    }
}
