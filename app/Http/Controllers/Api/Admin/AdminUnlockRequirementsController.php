<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ForumConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUnlockRequirementsController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'data' => [
                'unlock_req_min_posts' => (int) ForumConfig::get('unlock_req_min_posts', 0),
                'unlock_req_must_like' => (bool) ForumConfig::get('unlock_req_must_like', false),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'unlock_req_min_posts' => ['nullable', 'integer', 'min:0'],
            'unlock_req_must_like' => ['nullable', 'boolean'],
        ]);

        if (array_key_exists('unlock_req_min_posts', $validated)) {
            ForumConfig::set('unlock_req_min_posts', $validated['unlock_req_min_posts'] ?? 0);
        }

        if (array_key_exists('unlock_req_must_like', $validated)) {
            ForumConfig::set('unlock_req_must_like', $validated['unlock_req_must_like'] ? '1' : '0');
        }

        return response()->json(['message' => 'Unlock requirements updated.']);
    }
}
