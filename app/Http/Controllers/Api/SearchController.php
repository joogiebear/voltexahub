<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2'],
            'type' => ['nullable', 'string', 'in:threads,posts,users,all'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = $validated['q'];
        $type = $validated['type'] ?? 'all';
        $results = [];

        if ($type === 'all' || $type === 'threads') {
            $threads = Thread::where(function ($q) use ($query) {
                    $q->where('title', 'like', "%{$query}%")
                      ->orWhere('body', 'like', "%{$query}%");
                })
                ->whereHas('forum', fn ($q) => $q->where('is_active', true))
                ->with(['user:id,username,avatar_color', 'forum:id,name,slug'])
                ->select('id', 'forum_id', 'user_id', 'title', 'slug', 'reply_count', 'created_at')
                ->orderByDesc('created_at')
                ->paginate(10, ['*'], 'page', $request->input('page', 1));

            $results['threads'] = [
                'data' => $threads->items(),
                'total' => $threads->total(),
            ];
        }

        if ($type === 'all' || $type === 'posts') {
            $posts = Post::where('body', 'like', "%{$query}%")
                ->whereNull('deleted_at')
                ->whereHas('thread', fn ($q) => $q->whereHas('forum', fn ($f) => $f->where('is_active', true)))
                ->with(['user:id,username,avatar_color', 'thread:id,title,slug'])
                ->select('id', 'thread_id', 'user_id', 'body', 'created_at')
                ->orderByDesc('created_at')
                ->paginate(10, ['*'], 'page', $request->input('page', 1));

            $results['posts'] = [
                'data' => $posts->items(),
                'total' => $posts->total(),
            ];
        }

        if ($type === 'all' || $type === 'users') {
            $users = User::where('username', 'like', "%{$query}%")
                ->select('id', 'username', 'avatar_path', 'post_count', 'credits', 'created_at')
                ->orderByDesc('created_at')
                ->paginate(10, ['*'], 'page', $request->input('page', 1));

            $results['users'] = [
                'data' => $users->items(),
                'total' => $users->total(),
            ];
        }

        return response()->json([
            'data' => $results,
            'query' => $query,
        ]);
    }
}
