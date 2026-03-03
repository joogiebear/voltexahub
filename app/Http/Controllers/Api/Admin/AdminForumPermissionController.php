<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Forum;
use App\Models\ForumPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminForumPermissionController extends Controller
{
    private const ROLES = ['guest', 'member', 'vip', 'elite', 'moderator', 'admin'];

    public function index(Forum $forum): JsonResponse
    {
        $perms = [];
        foreach (self::ROLES as $role) {
            $p = ForumPermission::firstOrCreate(
                ['forum_id' => $forum->id, 'role_name' => $role],
                ['can_view' => true, 'can_post' => true, 'can_reply' => true]
            );
            $perms[] = [
                'role_name' => $role,
                'can_view'  => $p->can_view,
                'can_post'  => $p->can_post,
                'can_reply' => $p->can_reply,
            ];
        }

        return response()->json([
            'data' => [
                'forum'       => ['id' => $forum->id, 'name' => $forum->name],
                'permissions' => $perms,
            ],
        ]);
    }

    public function update(Request $request, Forum $forum): JsonResponse
    {
        $validated = $request->validate([
            'permissions'               => 'required|array',
            'permissions.*.role_name'   => 'required|string',
            'permissions.*.can_view'    => 'boolean',
            'permissions.*.can_post'    => 'boolean',
            'permissions.*.can_reply'   => 'boolean',
        ]);

        foreach ($validated['permissions'] as $p) {
            ForumPermission::updateOrCreate(
                ['forum_id' => $forum->id, 'role_name' => $p['role_name']],
                [
                    'can_view'  => $p['can_view']  ?? true,
                    'can_post'  => $p['can_post']   ?? true,
                    'can_reply' => $p['can_reply']  ?? true,
                ]
            );
        }

        return response()->json(['message' => 'Permissions updated.']);
    }
}
