<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PerkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserPerkController extends Controller
{
    public function __construct(protected PerkService $perkService) {}

    public function saveCustomCss(Request $request): JsonResponse
    {
        if (!$this->perkService->userHasPerk($request->user(), PerkService::CUSTOM_CSS)) {
            return response()->json(['message' => 'You do not have this perk.'], 403);
        }

        $validated = $request->validate([
            'css' => ['nullable', 'string', 'max:5000'],
        ]);

        $request->user()->update(['custom_css' => $validated['css']]);

        return response()->json(['message' => 'Custom CSS saved.']);
    }

    public function saveUsernameColor(Request $request): JsonResponse
    {
        if (!$this->perkService->userHasPerk($request->user(), PerkService::USERNAME_COLOR)) {
            return response()->json(['message' => 'You do not have this perk.'], 403);
        }

        $validated = $request->validate([
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $request->user()->update(['username_color' => $validated['color']]);

        return response()->json(['message' => 'Username color saved.']);
    }

    public function saveUserbarHue(Request $request): JsonResponse
    {
        if (!$this->perkService->userHasPerk($request->user(), PerkService::USERBAR_HUE)) {
            return response()->json(['message' => 'You do not have this perk.'], 403);
        }

        $validated = $request->validate([
            'hue' => ['nullable', 'integer', 'min:0', 'max:360'],
        ]);

        $request->user()->update(['userbar_hue' => $validated['hue']]);

        return response()->json(['message' => 'Userbar hue saved.']);
    }

    public function changeUsername(Request $request): JsonResponse
    {
        if (!$this->perkService->userHasPerk($request->user(), PerkService::CHANGE_USERNAME)) {
            return response()->json(['message' => 'You do not have this perk.'], 403);
        }

        $validated = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:30', 'alpha_num', 'unique:users,username'],
        ]);

        $user = $request->user();

        if ($user->username_changed_at && $user->username_changed_at->gt(now()->subDays(30))) {
            $nextDate = $user->username_changed_at->addDays(30)->toDateString();
            return response()->json([
                'message' => "You can change your username again after {$nextDate}.",
            ], 422);
        }

        $user->update([
            'username' => $validated['username'],
            'username_changed_at' => now(),
        ]);

        return response()->json(['message' => 'Username changed successfully.']);
    }

    public function saveAwardsOrder(Request $request): JsonResponse
    {
        if (!$this->perkService->userHasPerk($request->user(), PerkService::AWARDS_REORDER)) {
            return response()->json(['message' => 'You do not have this perk.'], 403);
        }

        $validated = $request->validate([
            'award_ids' => ['required', 'array'],
            'award_ids.*' => ['integer'],
        ]);

        $request->user()->update(['awards_sort_order' => $validated['award_ids']]);

        return response()->json(['message' => 'Awards order saved.']);
    }
}
