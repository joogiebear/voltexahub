<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['roles', 'activeCosmetic.storeItem']);

        $user->unread_notifications_count = $user->unreadNotifications()->count();
        $user->active_cosmetics = $user->cosmetics()
            ->where('is_active', true)
            ->with('storeItem')
            ->get();

        return response()->json([
            'data' => $user,
        ]);
    }

    public function profile(string $username): JsonResponse
    {
        $user = User::where('username', $username)
            ->firstOrFail();

        $user->load([
            'roles',
            'userAwards.award',
            'userAchievements.achievement',
            'activeCosmetic.storeItem',
        ]);

        $recentPosts = $user->posts()
            ->with('thread:id,title,slug')
            ->latest()
            ->take(5)
            ->get();

        return response()->json([
            'data' => [
                'user' => $user,
                'recent_posts' => $recentPosts,
            ],
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'user_title' => ['nullable', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'signature' => ['nullable', 'string', 'max:500'],
            'avatar_color' => ['nullable', 'string', 'max:7'],
            'discord_username' => ['nullable', 'string', 'max:100'],
            'twitter_handle' => ['nullable', 'string', 'max:100'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'minecraft_ign' => ['nullable', 'string', 'max:50'],
            'rust_steam_id' => ['nullable', 'string', 'max:50'],
        ]);

        $user->update($validated);

        return response()->json([
            'data' => $user->fresh(),
            'message' => 'Profile updated successfully.',
        ]);
    }

    public function updateAccount(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'current_password' => ['required_with:new_password', 'string'],
            'new_password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        if (isset($validated['name'])) {
            $user->name = $validated['name'];
        }

        if (isset($validated['email'])) {
            $user->email = $validated['email'];
        }

        if (! empty($validated['new_password'])) {
            if (! Hash::check($validated['current_password'], $user->password)) {
                return response()->json([
                    'message' => 'Current password is incorrect.',
                ], 422);
            }
            $user->password = $validated['new_password'];
        }

        $user->save();

        return response()->json([
            'data' => $user->fresh(),
            'message' => 'Account updated successfully.',
        ]);
    }

    public function credits(Request $request): JsonResponse
    {
        $user = $request->user();

        $log = $user->creditsLog()
            ->latest('created_at')
            ->paginate(20);

        return response()->json([
            'data' => [
                'balance' => $user->credits,
                'log' => $log->items(),
            ],
            'meta' => [
                'current_page' => $log->currentPage(),
                'last_page' => $log->lastPage(),
                'per_page' => $log->perPage(),
                'total' => $log->total(),
            ],
        ]);
    }

    public function achievements(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->checkAchievements();

        $achievements = $user->userAchievements()
            ->with('achievement')
            ->get();

        return response()->json([
            'data' => $achievements,
        ]);
    }

    public function awards(Request $request): JsonResponse
    {
        $awards = $request->user()->userAwards()
            ->with(['award', 'grantedByUser'])
            ->latest()
            ->get();

        return response()->json([
            'data' => $awards,
        ]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->notifications()
            ->paginate(20);

        return response()->json([
            'data' => $notifications->items(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    public function cosmetics(Request $request): JsonResponse
    {
        $cosmetics = $request->user()->cosmetics()
            ->with('storeItem')
            ->get();

        return response()->json([
            'data' => $cosmetics,
        ]);
    }

    public function toggleCosmetic(Request $request, int $id): JsonResponse
    {
        $cosmetic = $request->user()->cosmetics()->findOrFail($id);
        $cosmetic->update(['is_active' => ! $cosmetic->is_active]);

        return response()->json([
            'data' => $cosmetic->fresh(),
            'message' => 'Cosmetic toggled.',
        ]);
    }

    public function updateNotificationSettings(Request $request): JsonResponse
    {
        // Placeholder for notification preferences - can be extended
        return response()->json([
            'message' => 'Notification settings updated.',
        ]);
    }

    public function updatePrivacySettings(Request $request): JsonResponse
    {
        // Placeholder for privacy preferences - can be extended
        return response()->json([
            'message' => 'Privacy settings updated.',
        ]);
    }

    public function online(): JsonResponse
    {
        $users = User::where('last_seen', '>=', now()->subMinutes(5))
            ->select('id', 'username', 'avatar_path')
            ->orderByDesc('last_seen')
            ->get();

        return response()->json([
            'data' => $users,
            'count' => $users->count(),
        ]);
    }

    public function sessions(Request $request): JsonResponse
    {
        $sessions = $request->user()->sessions()
            ->latest('last_active_at')
            ->get();

        return response()->json([
            'data' => $sessions,
        ]);
    }

    public function destroySession(Request $request, int $id): JsonResponse
    {
        $session = $request->user()->sessions()->findOrFail($id);
        $session->delete();

        return response()->json([
            'message' => 'Session terminated.',
        ]);
    }
}
