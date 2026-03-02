<?php

namespace App\Http\Controllers\Api;

use App\Events\NewNotification;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\Thread;
use App\Models\User;
use App\Notifications\MentionNotification;
use App\Notifications\ThreadReplyNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function index(int $threadId): JsonResponse
    {
        $thread = Thread::findOrFail($threadId);

        $posts = $thread->posts()
            ->with([
                'user' => fn ($q) => $q->select(
                    'id', 'username', 'avatar_color', 'user_title',
                    'post_count', 'credits', 'created_at'
                ),
                'user.roles',
                'user.userAwards' => fn ($q) => $q->with('award')->take(4),
                'reactions',
            ])
            ->orderBy('created_at')
            ->paginate(10);

        return response()->json([
            'data' => $posts->items(),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
            ],
        ]);
    }

    public function store(Request $request, int $threadId): JsonResponse
    {
        $thread = Thread::findOrFail($threadId);

        if ($thread->is_locked) {
            return response()->json([
                'message' => 'This thread is locked.',
            ], 403);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:3'],
        ]);

        $user = $request->user();

        $post = $thread->posts()->create([
            'user_id' => $user->id,
            'body' => $validated['body'],
        ]);

        // Update thread counters
        $thread->increment('reply_count');
        $thread->update([
            'last_reply_at' => now(),
            'last_reply_user_id' => $user->id,
        ]);

        // Update forum counters
        $thread->forum->increment('post_count');
        $thread->forum->update([
            'last_post_at' => now(),
            'last_post_user_id' => $user->id,
        ]);

        if ($thread->subforum_id) {
            $thread->subforum->increment('post_count');
        }

        // Award credits and update user
        $user->addCredits(5, 'Posted a reply', Post::class, $post->id);
        $user->increment('post_count');
        $user->checkAchievements();

        // Notify thread owner of reply (if not self)
        $post->load('user');
        if ($thread->user_id !== $user->id) {
            $thread->user->notify(new ThreadReplyNotification($thread, $post));
            broadcast(new NewNotification($thread->user_id, [
                'type' => 'thread_reply',
                'title' => 'New reply to your thread',
                'body' => $user->username . ' replied to "' . $thread->title . '"',
                'url' => '/threads/' . $thread->id,
            ]));
        }

        // Parse @mentions and notify mentioned users
        preg_match_all('/@(\w+)/', $validated['body'], $matches);
        if (! empty($matches[1])) {
            $mentionedUsers = User::whereIn('username', $matches[1])
                ->where('id', '!=', $user->id)
                ->get();
            foreach ($mentionedUsers as $mentioned) {
                $mentioned->notify(new MentionNotification($post));
                broadcast(new NewNotification($mentioned->id, [
                    'type' => 'mention',
                    'title' => 'You were mentioned',
                    'body' => $user->username . ' mentioned you in a post',
                    'url' => '/threads/' . $post->thread_id,
                ]));
            }
        }

        return response()->json([
            'data' => $post->load([
                'user' => fn ($q) => $q->select(
                    'id', 'username', 'avatar_color', 'user_title',
                    'post_count', 'credits', 'created_at'
                ),
                'user.roles',
            ]),
            'message' => 'Reply posted successfully.',
        ], 201);
    }

    public function update(Request $request, int $postId): JsonResponse
    {
        $post = Post::findOrFail($postId);
        $user = $request->user();

        if ($post->user_id !== $user->id && ! $user->hasRole(['admin', 'moderator'])) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:3'],
        ]);

        $post->update([
            'body' => $validated['body'],
            'edited_at' => now(),
            'edit_count' => $post->edit_count + 1,
        ]);

        return response()->json([
            'data' => $post->fresh()->load([
                'user' => fn ($q) => $q->select(
                    'id', 'username', 'avatar_color', 'user_title',
                    'post_count', 'credits', 'created_at'
                ),
                'user.roles',
            ]),
            'message' => 'Post updated successfully.',
        ]);
    }

    public function react(Request $request, int $postId): JsonResponse
    {
        $post = Post::findOrFail($postId);

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:like,heart,laugh,fire'],
        ]);

        $user = $request->user();

        $existing = Reaction::where('post_id', $post->id)
            ->where('user_id', $user->id)
            ->where('type', $validated['type'])
            ->first();

        if ($existing) {
            $existing->delete();
            $post->decrement('reaction_count');

            return response()->json([
                'message' => 'Reaction removed.',
            ]);
        }

        Reaction::create([
            'post_id' => $post->id,
            'user_id' => $user->id,
            'type' => $validated['type'],
            'created_at' => now(),
        ]);

        $post->increment('reaction_count');

        return response()->json([
            'message' => 'Reaction added.',
        ], 201);
    }

    public function destroy(Request $request, int $postId): JsonResponse
    {
        $post = Post::findOrFail($postId);
        $user = $request->user();

        if ($post->user_id !== $user->id && ! $user->hasPermissionTo('delete-posts')) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        if ($post->is_first_post) {
            return response()->json([
                'message' => 'Cannot delete the first post. Delete the thread instead.',
            ], 403);
        }

        $post->delete();

        $post->thread->decrement('reply_count');
        $post->thread->forum->decrement('post_count');

        return response()->json([
            'message' => 'Post deleted successfully.',
        ]);
    }
}
