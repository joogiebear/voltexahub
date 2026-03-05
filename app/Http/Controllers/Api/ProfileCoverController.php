<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileCoverController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:5120', 'mimes:jpg,jpeg,png,gif,webp'],
        ]);

        $user = $request->user();

        if ($user->cover_photo_path) {
            Storage::disk('public')->delete($user->cover_photo_path);
        }

        $path = $request->file('image')->store('covers', 'public');
        $user->update(['cover_photo_path' => $path]);

        return response()->json(['cover_url' => $user->fresh()->cover_url]);
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
