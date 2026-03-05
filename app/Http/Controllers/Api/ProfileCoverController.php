<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ImageUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileCoverController extends Controller
{
    public function store(Request $request, ImageUploadService $imageService): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'max:10240'],
        ]);

        $user = $request->user();

        if ($user->cover_photo_path) {
            Storage::disk('public')->delete($user->cover_photo_path);
        }

        // Resize to 1500×500, crop to fit, convert to WebP
        $path = $imageService->store($request->file('image'), 'covers', 1500, 500, 85, true);
        $user->update(['cover_photo_path' => $path]);

        return response()->json([
            'cover_url' => $user->fresh()->cover_url,
            'cover_overlay_opacity' => $user->cover_overlay_opacity,
        ]);
    }

    public function updateOverlay(Request $request): JsonResponse
    {
        $request->validate(['opacity' => ['required', 'integer', 'min:0', 'max:80']]);
        $request->user()->update(['cover_overlay_opacity' => $request->opacity]);
        return response()->json(['cover_overlay_opacity' => $request->opacity]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->cover_photo_path) {
            Storage::disk('public')->delete($user->cover_photo_path);
        }

        $user->update(['cover_photo_path' => null]);

        return response()->json(['message' => 'Cover photo removed.']);
    }
}
