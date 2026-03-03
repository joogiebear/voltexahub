<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAnnouncementController extends Controller
{
    /**
     * List all announcements.
     */
    public function index(): JsonResponse
    {
        try {
            $announcements = Announcement::orderBy('sort_order')->get();

            return response()->json(['data' => $announcements]);
        } catch (\Illuminate\Database\QueryException) {
            return response()->json(['data' => []]);
        }
    }

    /**
     * Create a new announcement.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'text' => 'required|string',
            'type' => 'in:info,warning,success,danger',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $announcement = Announcement::create($validated);

        return response()->json(['data' => $announcement], 201);
    }

    /**
     * Update an announcement.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $announcement = Announcement::findOrFail($id);

        $validated = $request->validate([
            'text' => 'sometimes|required|string',
            'type' => 'in:info,warning,success,danger',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $announcement->update($validated);

        return response()->json(['data' => $announcement->fresh()]);
    }

    /**
     * Delete an announcement.
     */
    public function destroy(int $id): JsonResponse
    {
        $announcement = Announcement::findOrFail($id);
        $announcement->delete();

        return response()->json(['message' => 'Announcement deleted.']);
    }
}
