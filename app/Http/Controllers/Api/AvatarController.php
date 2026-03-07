<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ImageUploadService;
use App\Services\PlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AvatarController extends Controller
{
    // Recommended: 200×200 px, displayed at 48–96 px
    public function store(Request $request, ImageUploadService $imageService): JsonResponse
    {
        $plan = app(PlanService::class);
        $maxMb = $plan->maxUploadMb();
        $maxKb = $maxMb > 0 ? $maxMb * 1024 : 10240;

        $request->validate([
            'avatar' => ['required', 'file', "max:{$maxKb}"],
        ]);

        if ($maxMb > 0 && $request->file('avatar')->getSize() > $maxMb * 1024 * 1024) {
            return response()->json([
                'error'    => 'upload_limit_exceeded',
                'limit_mb' => $maxMb,
            ], 413);
        }

        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        // Crop to 300×300, convert to WebP
        $path = $imageService->store($request->file('avatar'), 'avatars', 300, 300, 85, true);

        $user->update(['avatar_path' => $path]);

        return response()->json([
            'data' => ['avatar_url' => $user->fresh()->avatar_url],
            'message' => 'Avatar updated',
        ]);
    }
}
