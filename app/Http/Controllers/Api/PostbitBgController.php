<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ImageUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PostbitBgController extends Controller
{
    // Recommended: 1000×250 px banner-style image
    public function upload(Request $request, ImageUploadService $imageService): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'mimes:jpeg,png,gif,webp', 'max:5120'],
        ]);

        $user = $request->user();

        if ($user->postbit_bg) {
            $oldPath = ltrim(str_replace('/storage', '', parse_url($user->postbit_bg, PHP_URL_PATH)), '/');
            Storage::disk('public')->delete($oldPath);
        }

        // Resize to 1000×250, crop to fit
        $path = $imageService->store($request->file('image'), 'postbit-bg', 1000, 250, 85, true);

        $user->update(['postbit_bg' => Storage::disk('public')->url($path)]);

        return response()->json([
            'data' => ['postbit_bg' => Storage::disk('public')->url($path)],
        ]);
    }

    public function remove(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->postbit_bg) {
            $oldPath = ltrim(str_replace('/storage', '', parse_url($user->postbit_bg, PHP_URL_PATH)), '/');
            Storage::disk('public')->delete($oldPath);
            $user->update(['postbit_bg' => null]);
        }

        return response()->json(['message' => 'Removed']);
    }
}
