<?php

namespace App\Http\Controllers\Api\Admin;

use App\Events\NewNotification;
use App\Http\Controllers\Controller;
use App\Models\Award;
use App\Models\User;
use App\Models\UserAward;
use App\Notifications\AwardReceivedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::with('roles');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role = $request->input('role')) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $role));
        }

        $users = $query->latest()->paginate(20);

        return response()->json([
            'data' => $users->items(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $user = User::with([
            'roles',
            'userAwards.award',
            'userAchievements.achievement',
        ])->findOrFail($id);

        $recentPosts = $user->posts()
            ->with('thread:id,title,slug')
            ->latest()
            ->take(10)
            ->get();

        return response()->json([
            'data' => [
                'user' => $user,
                'recent_posts' => $recentPosts,
            ],
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'role' => ['nullable', 'string', 'exists:roles,name'],
            'user_title' => ['nullable', 'string', 'max:100'],
        ]);

        if (isset($validated['user_title'])) {
            $user->update(['user_title' => $validated['user_title']]);
        }

        if (isset($validated['role'])) {
            $user->syncRoles([$validated['role']]);
        }

        return response()->json([
            'data' => $user->fresh()->load('roles'),
            'message' => 'User updated successfully.',
        ]);
    }

    public function ban(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
            'duration' => ['nullable', 'string', 'max:100'],
        ]);

        $user->assignRole('banned');
        $user->update([
            'user_title' => 'Banned' . ($validated['reason'] ? ': ' . $validated['reason'] : ''),
        ]);

        return response()->json([
            'data' => $user->fresh()->load('roles'),
            'message' => 'User banned successfully.',
        ]);
    }

    public function unban(int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->removeRole('banned');

        return response()->json([
            'data' => $user->fresh()->load('roles'),
            'message' => 'User unbanned successfully.',
        ]);
    }

    public function adjustCredits(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'amount' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $amount = $validated['amount'];
        $reason = $validated['reason'];

        if ($amount > 0) {
            $user->addCredits($amount, $reason);
        } elseif ($amount < 0) {
            $user->spendCredits(abs($amount), $reason);
        }

        return response()->json([
            'data' => [
                'credits' => $user->fresh()->credits,
            ],
            'message' => 'Credits adjusted successfully.',
        ]);
    }

    public function grantAward(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'award_id' => ['required', 'exists:awards,id'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $userAward = UserAward::create([
            'user_id' => $user->id,
            'award_id' => $validated['award_id'],
            'granted_by' => $request->user()->id,
            'reason' => $validated['reason'] ?? null,
        ]);

        $award = Award::find($validated['award_id']);
        $user->notify(new AwardReceivedNotification($award));
        broadcast(new NewNotification($user->id, [
            'type' => 'award_received',
            'title' => 'Award received!',
            'body' => 'You received the "' . $award->name . '" award',
            'url' => '/profile',
        ]));

        return response()->json([
            'data' => $userAward->load('award'),
            'message' => 'Award granted successfully.',
        ], 201);
    }

    public function revokeAward(int $id, int $awardId): JsonResponse
    {
        $userAward = UserAward::where('user_id', $id)
            ->where('id', $awardId)
            ->firstOrFail();

        $userAward->delete();

        return response()->json([
            'message' => 'Award revoked successfully.',
        ]);
    }

    public function banned(Request $request): JsonResponse
    {
        $users = User::whereHas('roles', fn ($q) => $q->where('name', 'banned'))
            ->with('roles')
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $users->items(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }
}
