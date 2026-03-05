<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Advertisement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminAdvertisementController extends Controller
{
    public function index(): JsonResponse
    {
        $ads = Advertisement::orderBy('display_order')->get();

        return response()->json(['data' => $ads]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'max:500'],
            'position' => ['required', 'in:header,sidebar,footer'],
            'display_order' => ['nullable', 'integer'],
            'image' => ['nullable', 'file', 'max:10240'],
        ]);

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('ads', 'public');
        }

        unset($validated['image']);

        $ad = Advertisement::create($validated);

        return response()->json(['data' => $ad, 'message' => 'Advertisement created.'], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $ad = Advertisement::findOrFail($id);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'max:500'],
            'position' => ['sometimes', 'in:header,sidebar,footer'],
            'display_order' => ['nullable', 'integer'],
            'image' => ['nullable', 'file', 'max:10240'],
        ]);

        if ($request->hasFile('image')) {
            if ($ad->image_path) {
                Storage::disk('public')->delete($ad->image_path);
            }
            $validated['image_path'] = $request->file('image')->store('ads', 'public');
        }

        unset($validated['image']);

        $ad->update($validated);

        return response()->json(['data' => $ad->fresh(), 'message' => 'Advertisement updated.']);
    }

    public function destroy(int $id): JsonResponse
    {
        $ad = Advertisement::findOrFail($id);

        if ($ad->image_path) {
            Storage::disk('public')->delete($ad->image_path);
        }

        $ad->delete();

        return response()->json(['message' => 'Advertisement deleted.']);
    }

    public function toggle(int $id): JsonResponse
    {
        $ad = Advertisement::findOrFail($id);
        $ad->update(['is_active' => !$ad->is_active]);

        return response()->json(['data' => $ad->fresh(), 'message' => 'Advertisement toggled.']);
    }
}
