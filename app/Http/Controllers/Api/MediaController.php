<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:5120', 'mimes:jpg,jpeg,png,gif,webp'],
        ]);

        $path = Storage::disk('public')->putFile('media', $request->file('image'));

        return response()->json([
            'data'    => ['url' => Storage::disk('public')->url($path)],
            'message' => 'Image uploaded',
        ]);
    }
}
