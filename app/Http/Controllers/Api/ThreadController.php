<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Forum;
use App\Models\Thread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ThreadController extends Controller
{
    public function index(Request $request, string $slug): JsonResponse
    {
        $forum = Forum::where('slug', $slug)->firstOrFail();

        $query = $forum->threads()
            ->with([
                'user:id,username,avatar_color',
                'user.roles',
                'lastReplyUser:id,username',
            ])
            ->orderByDesc('is_pinned')
            ->latest();

        if ($search = $request->input('search')) {
            $query->where('title', 'like', "%{$search}%");
        }

        $threads = $query->paginate(15);

        return response()->json([
            'data' => $threads->items(),
            'meta' => [
                'current_page' => $threads->currentPage(),
                'last_page' => $threads->lastPage(),
                'per_page' => $threads->perPage(),
                'total' => $threads->total(),
            ],
            'forum' => $forum->load('category.game'),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $thread = Thread::with(['user', 'forum.category', 'lastReplyUser'])
            ->findOrFail($id);

        $thread->increment('view_count');

        return response()->json([
            'data' => $thread,
        ]);
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
            'forum_id' => ['required', 'exists:forums,id'],
            'subforum_id' => ['nullable', 'exists:subforums,id'],
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'body' => ['required', 'string', 'min:10'],
        ]);

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
        $user->addCredits(10, 'Created a thread', Thread::class, $thread->id);
        $user->increment('post_count');
        $user->checkAchievements();

        return response()->json([
            'data' => $thread->load('user'),
            'message' => 'Thread created successfully.',
        ], 201);
    }
}
