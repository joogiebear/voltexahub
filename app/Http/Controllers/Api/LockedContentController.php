<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ForumConfig;
use App\Models\LockedContentUnlock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LockedContentController extends Controller
{
    public function unlock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content_hash' => ['required', 'string'],
            'cost' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        $hash = $validated['content_hash'];
        $cost = $validated['cost'] ?? (int) ForumConfig::get('locked_content_default_cost', 50);
        $taxPct = (int) ForumConfig::get('locked_content_tax_percent', 0);
        $total = (int) ceil($cost * (1 + $taxPct / 100));

        $existing = LockedContentUnlock::where('user_id', $user->id)
            ->where('content_hash', $hash)
            ->first();

        if ($existing) {
            return response()->json(['unlocked' => true, 'already_owned' => true]);
        }

        if ($user->credits < $total) {
            return response()->json(['message' => 'Insufficient credits.'], 422);
        }

        $user->spendCredits($total, 'Unlocked content', LockedContentUnlock::class, null);

        LockedContentUnlock::create([
            'user_id' => $user->id,
            'content_hash' => $hash,
            'credits_spent' => $total,
        ]);

        return response()->json(['unlocked' => true, 'credits_spent' => $total]);
    }

    public function check(Request $request): JsonResponse
    {
        $request->validate([
            'content_hash' => ['required', 'string'],
        ]);

        $unlocked = LockedContentUnlock::where('user_id', $request->user()->id)
            ->where('content_hash', $request->content_hash)
            ->exists();

        return response()->json(['unlocked' => $unlocked]);
    }
}
