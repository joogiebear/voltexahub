<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ForumConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class AdminGroupController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::all()->map(fn ($role) => $this->formatRole($role));

        return response()->json([
            'data' => $roles,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'color' => ['nullable', 'string', 'max:50'],
            'label' => ['nullable', 'string', 'max:255'],
        ]);

        $role = Role::create(['name' => $validated['name'], 'guard_name' => 'web']);

        if (! empty($validated['color'])) {
            ForumConfig::set("group_color_{$role->name}", $validated['color']);
        }
        if (! empty($validated['label'])) {
            ForumConfig::set("group_label_{$role->name}", $validated['label']);
        }

        return response()->json([
            'data' => $this->formatRole($role),
            'message' => 'Group created successfully.',
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $role = Role::findOrFail($id);

        $validated = $request->validate([
            'color' => ['nullable', 'string', 'max:50'],
            'label' => ['nullable', 'string', 'max:255'],
        ]);

        ForumConfig::set("group_color_{$role->name}", $validated['color'] ?? '');
        ForumConfig::set("group_label_{$role->name}", $validated['label'] ?? '');

        return response()->json([
            'data' => $this->formatRole($role),
            'message' => 'Group updated successfully.',
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $role = Role::findOrFail($id);

        ForumConfig::where('key', "group_color_{$role->name}")->delete();
        ForumConfig::where('key', "group_label_{$role->name}")->delete();

        $role->delete();

        return response()->json([
            'message' => 'Group deleted successfully.',
        ]);
    }

    private function formatRole(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'color' => ForumConfig::get("group_color_{$role->name}"),
            'label' => ForumConfig::get("group_label_{$role->name}"),
            'created_at' => $role->created_at,
            'updated_at' => $role->updated_at,
        ];
    }
}
