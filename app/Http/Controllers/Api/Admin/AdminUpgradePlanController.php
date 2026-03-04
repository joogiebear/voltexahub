<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\UpgradePlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminUpgradePlanController extends Controller
{
    public function index(): JsonResponse
    {
        $plans = UpgradePlan::with('requiredPlan:id,name,color')->orderBy('display_order')->orderBy('price')->get();
        return response()->json(['data' => $plans]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:100',
            'description'     => 'nullable|string',
            'color'           => 'nullable|string|max:20',
            'price'           => 'required|numeric|min:0',
            'term'            => 'required|in:lifetime,monthly,yearly',
            'role_name'       => 'nullable|string|max:100',
            'rep_power_pos'   => 'integer|min:0',
            'rep_power_neg'   => 'integer|min:0',
            'rep_daily_limit' => 'integer|min:0',
            'features'        => 'nullable|array',
            'one_time_bonus'  => 'nullable|array',
            'stripe_price_id' => 'nullable|string',
            'display_order'   => 'integer',
            'is_active'       => 'boolean',
            'is_featured'     => 'boolean',
            'required_plan_id' => 'nullable|exists:upgrade_plans,id',
        ]);

        $validated['slug'] = Str::slug($validated['name']) . '-' . Str::random(4);

        $plan = UpgradePlan::create($validated);

        return response()->json(['data' => $plan, 'message' => 'Plan created.'], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $plan = UpgradePlan::findOrFail($id);

        $validated = $request->validate([
            'name'            => 'sometimes|string|max:100',
            'description'     => 'nullable|string',
            'color'           => 'nullable|string|max:20',
            'price'           => 'sometimes|numeric|min:0',
            'term'            => 'sometimes|in:lifetime,monthly,yearly',
            'role_name'       => 'nullable|string|max:100',
            'rep_power_pos'   => 'integer|min:0',
            'rep_power_neg'   => 'integer|min:0',
            'rep_daily_limit' => 'integer|min:0',
            'features'        => 'nullable|array',
            'one_time_bonus'  => 'nullable|array',
            'stripe_price_id' => 'nullable|string',
            'display_order'   => 'integer',
            'is_active'        => 'boolean',
            'is_featured'      => 'boolean',
            'required_plan_id' => 'nullable|exists:upgrade_plans,id',
        ]);

        $plan->update($validated);

        return response()->json(['data' => $plan, 'message' => 'Plan updated.']);
    }

    public function destroy(int $id): JsonResponse
    {
        UpgradePlan::findOrFail($id)->delete();
        return response()->json(['message' => 'Plan deleted.']);
    }
}
