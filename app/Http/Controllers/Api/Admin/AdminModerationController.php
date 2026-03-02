<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Thread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminModerationController extends Controller
{
    public function reports(): JsonResponse
    {
        $posts = Post::withTrashed()
            ->with(['user:id,username', 'thread:id,title,slug'])
            ->latest()
            ->take(20)
            ->get();

        return response()->json([
            'data' => $posts,
        ]);
    }

    public function threads(Request $request): JsonResponse
    {
        $query = Thread::with(['user:id,username', 'forum:id,name,slug']);

        if ($request->has('is_pinned')) {
            $query->where('is_pinned', $request->boolean('is_pinned'));
        }

        if ($request->has('is_locked')) {
            $query->where('is_locked', $request->boolean('is_locked'));
        }

        if ($request->has('is_solved')) {
            $query->where('is_solved', $request->boolean('is_solved'));
        }

        if ($search = $request->input('search')) {
            $query->where('title', 'like', "%{$search}%");
        }

        $threads = $query->latest()->paginate(20);

        return response()->json([
            'data' => $threads->items(),
            'meta' => [
                'current_page' => $threads->currentPage(),
                'last_page' => $threads->lastPage(),
                'per_page' => $threads->perPage(),
                'total' => $threads->total(),
            ],
        ]);
    }

    public function pinThread(int $id): JsonResponse
    {
        $thread = Thread::findOrFail($id);
        $thread->update(['is_pinned' => ! $thread->is_pinned]);

        return response()->json([
            'data' => $thread->fresh(),
            'message' => $thread->is_pinned ? 'Thread pinned.' : 'Thread unpinned.',
        ]);
    }

    public function lockThread(int $id): JsonResponse
    {
        $thread = Thread::findOrFail($id);
        $thread->update(['is_locked' => ! $thread->is_locked]);

        return response()->json([
            'data' => $thread->fresh(),
            'message' => $thread->is_locked ? 'Thread locked.' : 'Thread unlocked.',
        ]);
    }

    public function solveThread(int $id): JsonResponse
    {
        $thread = Thread::findOrFail($id);
        $thread->update(['is_solved' => ! $thread->is_solved]);

        return response()->json([
            'data' => $thread->fresh(),
            'message' => $thread->is_solved ? 'Thread marked as solved.' : 'Thread marked as unsolved.',
        ]);
    }

    public function deletePost(int $id): JsonResponse
    {
        $post = Post::findOrFail($id);

        $thread = $post->thread;
        $forum = $thread->forum;

        $post->forceDelete();

        $thread->decrement('reply_count');
        $forum->decrement('post_count');

        return response()->json([
            'message' => 'Post permanently deleted.',
        ]);
    }

    public function deleteThread(int $id): JsonResponse
    {
        $thread = Thread::findOrFail($id);
        $forum = $thread->forum;

        $postCount = $thread->posts()->count();
        $thread->posts()->forceDelete();
        $thread->delete();

        $forum->decrement('thread_count');
        $forum->decrement('post_count', $postCount);

        return response()->json([
            'message' => 'Thread deleted.',
        ]);
    }

    public function moveThread(Request $request, int $id): JsonResponse
    {
        $thread = Thread::findOrFail($id);
        $validated = $request->validate(['forum_id' => ['required', 'exists:forums,id']]);

        $oldForum = $thread->forum;
        $postCount = $thread->posts()->count();

        $thread->update(['forum_id' => $validated['forum_id']]);

        $oldForum->decrement('thread_count');
        $oldForum->decrement('post_count', $postCount);

        $newForum = $thread->fresh()->forum;
        $newForum->increment('thread_count');
        $newForum->increment('post_count', $postCount);

        return response()->json([
            'message' => 'Thread moved successfully.',
            'data' => $thread->fresh()->load('forum:id,name,slug'),
        ]);
    }
}
