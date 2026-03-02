<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ForumConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminConfigController extends Controller
{
    public function index(): JsonResponse
    {
        $configs = ForumConfig::all()->pluck('value', 'key');

        return response()->json([
            'data' => $configs,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'config' => ['required', 'array'],
            'config.*' => ['nullable'],
        ]);

        foreach ($validated['config'] as $key => $value) {
            ForumConfig::set($key, is_bool($value) ? ($value ? 'true' : 'false') : (string)($value ?? ''));
        }

        $configs = ForumConfig::all()->pluck('value', 'key');

        return response()->json([
            'data' => $configs,
            'message' => 'Configuration updated successfully.',
        ]);
    }
}
