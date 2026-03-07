<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $plan = app(PlanService::class);
        $maxMb = $plan->maxUploadMb();
        $maxKb = $maxMb > 0 ? $maxMb * 1024 : 5120;

        $request->validate([
            'image' => ['required', 'image', "max:{$maxKb}", 'mimes:jpg,jpeg,png,gif,webp'],
        ]);

        if ($maxMb > 0 && $request->file('image')->getSize() > $maxMb * 1024 * 1024) {
            return response()->json([
                'error'    => 'upload_limit_exceeded',
                'limit_mb' => $maxMb,
            ], 413);
        }

        $path = Storage::disk('public')->putFile('media', $request->file('image'));

        return response()->json([
            'data'    => ['url' => Storage::disk('public')->url($path)],
            'message' => 'Image uploaded',
        ]);
    }
}
