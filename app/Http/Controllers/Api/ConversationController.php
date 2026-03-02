<?php

namespace App\Http\Controllers\Api;

use App\Events\NewMessage;
use App\Events\NewNotification;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Notifications\DMReceivedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversations = $user->conversations()
            ->with(['users' => fn ($q) => $q->select('users.id', 'username', 'avatar_color')
                ->where('users.id', '!=', $user->id)])
            ->get()
            ->map(function ($conversation) use ($user) {
                $lastMessage = $conversation->messages()->with('sender:id,username')->latest()->first();
                $pivot = $conversation->pivot;
                $unreadCount = $conversation->messages()
                    ->where('sender_id', '!=', $user->id)
                    ->when($pivot->last_read_at, fn ($q) => $q->where('created_at', '>', $pivot->last_read_at))
                    ->when(! $pivot->last_read_at, fn ($q) => $q)
                    ->count();

                return [
                    'id' => $conversation->id,
                    'other_user' => $conversation->users->first(),
                    'last_message' => $lastMessage ? [
                        'body' => $lastMessage->body,
                        'sender' => $lastMessage->sender,
                        'created_at' => $lastMessage->created_at,
                    ] : null,
                    'unread_count' => $unreadCount,
                    'updated_at' => $conversation->updated_at,
                ];
            })
            ->sortByDesc(fn ($c) => $c['last_message']['created_at'] ?? $c['updated_at'])
            ->values();

        return response()->json([
            'data' => $conversations,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $user = $request->user();
        $otherUserId = $validated['user_id'];

        if ($user->id === (int) $otherUserId) {
            return response()->json([
                'message' => 'Cannot start a conversation with yourself.',
            ], 422);
        }

        // Check for existing conversation between these two users
        $existing = Conversation::whereHas('users', fn ($q) => $q->where('users.id', $user->id))
            ->whereHas('users', fn ($q) => $q->where('users.id', $otherUserId))
            ->first();

        if ($existing) {
            return response()->json([
                'data' => ['id' => $existing->id],
                'message' => 'Conversation already exists.',
            ]);
        }

        $conversation = Conversation::create();
        $conversation->users()->attach([$user->id, $otherUserId]);

        return response()->json([
            'data' => ['id' => $conversation->id],
            'message' => 'Conversation created.',
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $conversation = Conversation::whereHas('users', fn ($q) => $q->where('users.id', $user->id))
            ->findOrFail($id);

        // Mark as read
        $conversation->users()->updateExistingPivot($user->id, [
            'last_read_at' => now(),
        ]);

        $messages = $conversation->messages()
            ->with('sender:id,username,avatar_color')
            ->latest()
            ->paginate(30);

        return response()->json([
            'data' => $messages->items(),
            'meta' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ],
        ]);
    }

    public function sendMessage(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $conversation = Conversation::whereHas('users', fn ($q) => $q->where('users.id', $user->id))
            ->findOrFail($id);

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:1'],
        ]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'body' => $validated['body'],
        ]);

        $message->load('sender:id,username,avatar_color');

        // Update conversation timestamp
        $conversation->touch();

        // Mark as read for sender
        $conversation->users()->updateExistingPivot($user->id, [
            'last_read_at' => now(),
        ]);

        // Notify the other user
        $otherUser = $conversation->users()->where('users.id', '!=', $user->id)->first();
        if ($otherUser) {
            $otherUser->notify(new DMReceivedNotification($message));
            broadcast(new NewNotification($otherUser->id, [
                'type' => 'dm_received',
                'title' => 'New message',
                'body' => $user->username . ' sent you a message',
                'url' => '/messages/' . $conversation->id,
            ]));
            broadcast(new NewMessage(
                $otherUser->id,
                $conversation->id,
                ['id' => $user->id, 'username' => $user->username],
                \Illuminate\Support\Str::limit($validated['body'], 100),
            ));
        }

        return response()->json([
            'data' => $message,
            'message' => 'Message sent.',
        ], 201);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();

        $count = 0;
        $conversations = $user->conversations()->get();

        foreach ($conversations as $conversation) {
            $pivot = $conversation->pivot;
            $count += $conversation->messages()
                ->where('sender_id', '!=', $user->id)
                ->when($pivot->last_read_at, fn ($q) => $q->where('created_at', '>', $pivot->last_read_at))
                ->when(! $pivot->last_read_at, fn ($q) => $q)
                ->count();
        }

        return response()->json([
            'data' => ['count' => $count],
        ]);
    }
}
