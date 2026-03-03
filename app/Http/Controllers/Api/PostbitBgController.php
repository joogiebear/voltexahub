<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PostbitBgController extends Controller
{
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'mimes:jpeg,png,gif,webp', 'max:5120'],
        ]);

        $user = $request->user();
        $file = $request->file('image');
        $ext = $file->getClientOriginalExtension();

        // Delete old file if exists
        if ($user->postbit_bg) {
            $oldPath = str_replace('/storage/', '', $user->postbit_bg);
            Storage::disk('public')->delete($oldPath);
        }

        $path = $file->storeAs('postbit-bg', $user->id . '.' . $ext, 'public');

        $user->update(['postbit_bg' => '/storage/' . $path]);

        return response()->json([
            'data' => [
                'postbit_bg' => '/storage/' . $path,
            ],
        ]);
    }

    public function remove(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->postbit_bg) {
            $oldPath = str_replace('/storage/', '', $user->postbit_bg);
            Storage::disk('public')->delete($oldPath);
            $user->update(['postbit_bg' => null]);
        }

        return response()->json([
            'message' => 'Removed',
        ]);
    }
}
