<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\JsonResponse;

class ForumController extends Controller
{
    public function index(): JsonResponse
    {
        $games = Game::with([
            'categories' => fn ($q) => $q->where('is_active', true)
                ->orderBy('display_order')
                ->with([
                    'forums' => fn ($q) => $q->where('is_active', true)
                        ->orderBy('display_order')
                        ->withCount('threads')
                        ->with('lastPostUser:id,username,avatar_color'),
                ]),
        ])
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();

        return response()->json([
            'data' => $games,
        ]);
    }
}
