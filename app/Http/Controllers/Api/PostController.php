<?php

namespace App\Http\Controllers\Api;

use App\Events\NewNotification;
use App\Http\Controllers\Controller;
use App\Models\ForumConfig;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\Reaction;
use App\Models\Thread;
use App\Models\ThreadSubscription;
use App\Models\User;
use App\Notifications\MentionNotification;
use App\Notifications\ThreadReplyNotification;
use App\Notifications\ThreadSubscriptionNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function index(string $threadId): JsonResponse
    {
        $thread = Thread::where(is_numeric($threadId) ? 'id' : 'slug', $threadId)->firstOrFail();

        $posts = $thread->posts()
            ->with([
                'user' => fn ($q) => $q->select(
                    'id', 'username', 'avatar_color', 'avatar_path', 'postbit_bg', 'user_title',
                    'post_count', 'credits', 'created_at'
                ),
                'user.roles',
                'user.userAwards' => fn ($q) => $q->with('award')->take(4),
                'reactions',
            ])
            ->orderBy('created_at')
            ->paginate(10);

        // Batch-load post like data
        $postIds = collect($posts->items())->pluck('id');
        $likeCounts = PostLike::whereIn('post_id', $postIds)
            ->groupBy('post_id')
            ->selectRaw('post_id, count(*) as count')
            ->pluck('count', 'post_id');
        $likedByMe = auth()->check()
            ? PostLike::where('user_id', auth()->id())->whereIn('post_id', $postIds)->pluck('post_id')->flip()
            : collect();

        $items = collect($posts->items())->map(function ($post) use ($likeCounts, $likedByMe) {
            $post = $post->toArray();
            $post['like_count'] = $likeCounts[$post['id']] ?? 0;
            $post['is_liked_by_me'] = isset($likedByMe[$post['id']]);
            return $post;
        });

        return response()->json([
            'data' => $items->values(),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
            ],
        ]);
    }

    public function store(Request $request, string $threadId): JsonResponse
    {
        $thread = Thread::where(is_numeric($threadId) ? 'id' : 'slug', $threadId)->firstOrFail();

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
        $user->addCredits((int) ForumConfig::get('credits_per_reply', 5), 'Posted a reply', Post::class, $post->id);
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

        // Notify thread subscribers (except the post author and thread owner who was already notified)
        $subscriberIds = ThreadSubscription::where('thread_id', $thread->id)
            ->where('user_id', '!=', $user->id)
            ->where('user_id', '!=', $thread->user_id)
            ->pluck('user_id');

        $subscribers = User::whereIn('id', $subscriberIds)->get();
        foreach ($subscribers as $subscriber) {
            $subscriber->notify(new ThreadSubscriptionNotification($thread, $post));
            broadcast(new NewNotification($subscriber->id, [
                'type' => 'thread_reply',
                'title' => 'New reply in subscribed thread',
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

        $postData = $post->load([
            'user' => fn ($q) => $q->select(
                'id', 'username', 'avatar_color', 'avatar_path', 'user_title',
                'post_count', 'credits', 'created_at'
            ),
            'user.roles',
        ])->toArray();
        $postData['like_count'] = 0;
        $postData['is_liked_by_me'] = false;

        return response()->json([
            'data' => $postData,
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

        $updatedPost = $post->fresh()->load([
            'user' => fn ($q) => $q->select(
                'id', 'username', 'avatar_color', 'avatar_path', 'user_title',
                'post_count', 'credits', 'created_at'
            ),
            'user.roles',
        ])->toArray();
        $updatedPost['like_count'] = PostLike::where('post_id', $post->id)->count();
        $updatedPost['is_liked_by_me'] = auth()->check() && PostLike::where('user_id', auth()->id())->where('post_id', $post->id)->exists();

        return response()->json([
            'data' => $updatedPost,
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

        // Award credits to post author for receiving a reaction
        $creditsPerLike = (int) ForumConfig::get('credits_per_like', 1);
        if ($creditsPerLike > 0 && $post->user_id !== $user->id) {
            $post->user->addCredits($creditsPerLike, 'Received a like', Post::class, $post->id);
        }

        return response()->json([
            'message' => 'Reaction added.',
        ], 201);
    }

    public function likePost(Request $request, int $postId): JsonResponse
    {
        $post = Post::findOrFail($postId);
        $userId = auth()->id();

        $existing = PostLike::where('user_id', $userId)->where('post_id', $postId)->first();

        if ($existing) {
            $existing->delete();
            $liked = false;
        } else {
            PostLike::create(['user_id' => $userId, 'post_id' => $postId]);
            $liked = true;

            // Award credits to post author (reuse credits_per_like config, skip self-likes)
            if ($post->user_id !== $userId) {
                $creditsPerLike = (int) ForumConfig::get('credits_per_like', 1);
                if ($creditsPerLike > 0) {
                    $post->user->addCredits($creditsPerLike, 'Post liked', Post::class, $post->id);
                }
            }
        }

        $likeCount = PostLike::where('post_id', $postId)->count();

        return response()->json([
            'data' => [
                'liked' => $liked,
                'like_count' => $likeCount,
            ]
        ]);
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
