<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ForumConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminLogoController extends Controller
{
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => ['required', 'file', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
        ]);

        // Delete old logo if exists
        $oldPath = ForumConfig::get('logo_image', '');
        if ($oldPath) {
            $relative = str_replace('/storage/', '', $oldPath);
            Storage::disk('public')->delete($relative);
        }

        $file = $request->file('logo');
        $filename = uniqid('logo_') . '.' . $file->getClientOriginalExtension();
        $file->storeAs('logos', $filename, 'public');

        $url = '/storage/logos/' . $filename;
        ForumConfig::set('logo_image', $url);

        return response()->json([
            'data' => ['logo_image' => $url],
        ]);
    }

    public function remove(): JsonResponse
    {
        $path = ForumConfig::get('logo_image', '');
        if ($path) {
            $relative = str_replace('/storage/', '', $path);
            Storage::disk('public')->delete($relative);
        }

        ForumConfig::set('logo_image', '');

        return response()->json([
            'message' => 'Logo removed',
        ]);
    }
}
