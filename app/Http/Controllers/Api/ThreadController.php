<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Forum;
use App\Models\ForumConfig;
use App\Models\Thread;
use App\Models\ThreadLike;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ThreadController extends Controller
{
    public function index(Request $request, string $slug): JsonResponse
    {
        $forum = Forum::with(['category', 'parentForum'])->where('slug', $slug)->firstOrFail();

        $query = $forum->threads()
            ->with([
                'user:id,username,avatar_color,avatar_path',
                'user.roles',
                'lastReplyUser:id,username,avatar_color,avatar_path',
                'lastReplyUser.roles',
            ])
            ->orderByDesc('is_pinned')
            ->latest();

        if ($search = $request->input('search')) {
            $query->where('title', 'like', "%{$search}%");
        }

        $threads = $query->paginate(15);

        $forumData = $forum->toArray();
        $forumData['breadcrumb'] = [
            'category' => ['id' => $forum->category->id, 'name' => $forum->category->name],
            'parent_forum' => $forum->parentForum ? [
                'id' => $forum->parentForum->id,
                'name' => $forum->parentForum->name,
                'slug' => $forum->parentForum->slug,
            ] : null,
        ];

        return response()->json([
            'data' => $threads->items(),
            'meta' => [
                'current_page' => $threads->currentPage(),
                'last_page' => $threads->lastPage(),
                'per_page' => $threads->perPage(),
                'total' => $threads->total(),
            ],
            'forum' => $forumData,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $thread = Thread::with([
                'user', 'user.roles', 'forum.category', 'forum.parentForum', 'lastReplyUser', 'lastReplyUser.roles',
            ])
            ->where(is_numeric($id) ? 'id' : 'slug', $id)
            ->firstOrFail();

        $thread->increment('view_count');

        $threadData = $thread->toArray();
        $forum = $thread->forum;
        $threadData['breadcrumb'] = [
            'category' => ['id' => $forum->category->id, 'name' => $forum->category->name],
            'parent_forum' => $forum->parentForum ? [
                'id' => $forum->parentForum->id,
                'name' => $forum->parentForum->name,
                'slug' => $forum->parentForum->slug,
            ] : null,
        ];
        $threadData['likers'] = $thread->likes()->with('user:id,username,avatar_color,avatar_path')->get()->map(fn($l) => [
            'id' => $l->user->id ?? $l->user_id,
            'username' => $l->user->username ?? 'Unknown',
            'avatar_url' => $l->user->avatar_url ?? null,
        ]);
        $threadData['is_liked_by_me'] = auth()->check() ? $thread->likes()->where('user_id', auth()->id())->exists() : false;

        return response()->json(['data' => $threadData]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $thread = Thread::findOrFail($id);
        $user = $request->user();

        if ($thread->user_id !== $user->id && ! $user->hasRole('admin')) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'body' => ['nullable', 'string', 'min:3'],
        ]);

        $thread->update([
            'title' => $validated['title'],
        ]);

        if (! empty($validated['body'])) {
            $firstPost = $thread->posts()->where('is_first_post', true)->first();
            if ($firstPost) {
                $firstPost->update([
                    'body' => $validated['body'],
                    'edited_at' => now(),
                    'edit_count' => $firstPost->edit_count + 1,
                ]);
            }
            $thread->update(['body' => $validated['body']]);
        }

        return response()->json([
            'data' => $thread->fresh()->load('user'),
            'message' => 'Thread updated successfully.',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'forum_id' => ['nullable', 'integer', 'exists:forums,id'],
            'forum_slug' => ['nullable', 'string', 'exists:forums,slug'],
            'subforum_id' => ['nullable', 'exists:subforums,id'],
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'body' => ['required', 'string', 'min:10'],
        ]);

        // Resolve forum_id from slug if not provided directly
        if (empty($validated['forum_id']) && !empty($validated['forum_slug'])) {
            $forum = \App\Models\Forum::where('slug', $validated['forum_slug'])->firstOrFail();
            $validated['forum_id'] = $forum->id;
        }

        if (empty($validated['forum_id'])) {
            return response()->json(['message' => 'Forum is required.'], 422);
        }

        $user = $request->user();

        $thread = Thread::create([
            'forum_id' => $validated['forum_id'],
            'subforum_id' => $validated['subforum_id'] ?? null,
            'user_id' => $user->id,
            'title' => $validated['title'],
            'slug' => Str::slug($validated['title']) . '-' . Str::random(6),
            'body' => $validated['body'],
        ]);

        // Create the first post
        $thread->posts()->create([
            'user_id' => $user->id,
            'body' => $validated['body'],
            'is_first_post' => true,
        ]);

        // Increment forum counters
        $thread->forum->increment('thread_count');
        $thread->forum->increment('post_count');
        $thread->forum->update([
            'last_post_at' => now(),
            'last_post_user_id' => $user->id,
        ]);

        if ($thread->subforum_id) {
            $thread->subforum->increment('thread_count');
            $thread->subforum->increment('post_count');
        }

        // Award credits
        $user->addCredits((int) ForumConfig::get('credits_per_thread', 10), 'Created a thread', Thread::class, $thread->id);
        $user->increment('post_count');
        $user->checkAchievements();

        return response()->json([
            'data' => $thread->load('user'),
            'message' => 'Thread created successfully.',
        ], 201);
    }

    public function like(Request $request, int $id): JsonResponse
    {
        $thread = Thread::findOrFail($id);
        $user = $request->user();

        $existing = ThreadLike::where('user_id', $user->id)
            ->where('thread_id', $thread->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $liked = false;
        } else {
            ThreadLike::create([
                'user_id' => $user->id,
                'thread_id' => $thread->id,
            ]);
            $liked = true;
        }

        $likers = $thread->likes()
            ->with('user:id,username,avatar_path')
            ->get()
            ->pluck('user')
            ->map(fn ($u) => [
                'id' => $u->id,
                'username' => $u->username,
                'avatar_url' => $u->avatar_url,
            ]);

        return response()->json([
            'liked' => $liked,
            'likes_count' => $thread->likes()->count(),
            'likers' => $likers,
        ]);
    }
}
