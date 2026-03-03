<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;

class AnnouncementController extends Controller
{
    /**
     * Return active announcements ordered by sort_order.
     */
    public function index(): JsonResponse
    {
        try {
            $announcements = Announcement::where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'text', 'type', 'sort_order']);

            return response()->json(['data' => $announcements]);
        } catch (\Illuminate\Database\QueryException) {
            // Table may not exist if plugin not installed yet
            return response()->json(['data' => []]);
        }
    }
}
