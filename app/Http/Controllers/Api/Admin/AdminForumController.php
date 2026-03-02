<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Forum;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminForumController extends Controller
{
    public function tree(): JsonResponse
    {
        $games = Game::with([
            'categories' => fn ($q) => $q->orderBy('display_order')->with([
                'forums' => fn ($q) => $q->orderBy('display_order')->with([
                    'subforums' => fn ($q) => $q->orderBy('display_order'),
                    'lastPostUser:id,username',
                ]),
            ]),
        ])->orderBy('display_order')->get();

        return response()->json([
            'data' => $games,
        ]);
    }

    public function createGame(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:games,slug'],
            'icon' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'display_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['name']);
        $validated['is_active'] = $validated['is_active'] ?? true;

        $game = Game::create($validated);

        return response()->json([
            'data' => $game,
            'message' => 'Game created successfully.',
        ], 201);
    }

    public function updateGame(Request $request, int $id): JsonResponse
    {
        $game = Game::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', 'unique:games,slug,' . $id],
            'icon' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'display_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $game->update($validated);

        return response()->json([
            'data' => $game->fresh(),
            'message' => 'Game updated successfully.',
        ]);
    }

    public function deleteGame(int $id): JsonResponse
    {
        $game = Game::findOrFail($id);
        $game->delete();

        return response()->json([
            'message' => 'Game deleted successfully.',
        ]);
    }

    public function createCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'game_id' => ['required', 'exists:games,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:categories,slug'],
            'description' => ['nullable', 'string'],
            'display_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['name']);
        $validated['is_active'] = $validated['is_active'] ?? true;

        $category = Category::create($validated);

        return response()->json([
            'data' => $category->load('game'),
            'message' => 'Category created successfully.',
        ], 201);
    }

    public function updateCategory(Request $request, int $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        $validated = $request->validate([
            'game_id' => ['sometimes', 'exists:games,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', 'unique:categories,slug,' . $id],
            'description' => ['nullable', 'string'],
            'display_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $category->update($validated);

        return response()->json([
            'data' => $category->fresh()->load('game'),
            'message' => 'Category updated successfully.',
        ]);
    }

    public function deleteCategory(int $id): JsonResponse
    {
        $category = Category::findOrFail($id);
        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully.',
        ]);
    }

    public function createForum(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:forums,slug'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:255'],
            'display_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['name']);
        $validated['icon'] = $validated['icon'] ?? 'fa-solid fa-comment';
        $validated['is_active'] = $validated['is_active'] ?? true;

        $forum = Forum::create($validated);

        return response()->json([
            'data' => $forum->load('category.game'),
            'message' => 'Forum created successfully.',
        ], 201);
    }

    public function updateForum(Request $request, int $id): JsonResponse
    {
        $forum = Forum::findOrFail($id);

        $validated = $request->validate([
            'category_id' => ['sometimes', 'exists:categories,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', 'unique:forums,slug,' . $id],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:255'],
            'display_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        // If slug sent but empty, regenerate from name
        if (array_key_exists("slug", $validated) && empty($validated["slug"])) {
            $validated["slug"] = Str::slug($validated["name"] ?? $forum->name);
        }

        $forum->update($validated);

        return response()->json([
            'data' => $forum->fresh()->load('category.game'),
            'message' => 'Forum updated successfully.',
        ]);
    }

    public function deleteForum(int $id): JsonResponse
    {
        $forum = Forum::findOrFail($id);
        $forum->delete();

        return response()->json([
            'message' => 'Forum deleted successfully.',
        ]);
    }

    public function reorderGames(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'exists:games,id'],
            'items.*.display_order' => ['required', 'integer'],
        ]);

        foreach ($validated['items'] as $item) {
            Game::where('id', $item['id'])->update(['display_order' => $item['display_order']]);
        }

        return response()->json(['message' => 'Reordered successfully']);
    }

    public function reorderCategories(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'exists:categories,id'],
            'items.*.display_order' => ['required', 'integer'],
        ]);

        foreach ($validated['items'] as $item) {
            Category::where('id', $item['id'])->update(['display_order' => $item['display_order']]);
        }

        return response()->json(['message' => 'Reordered successfully']);
    }

    public function reorderForums(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'exists:forums,id'],
            'items.*.display_order' => ['required', 'integer'],
        ]);

        foreach ($validated['items'] as $item) {
            Forum::where('id', $item['id'])->update(['display_order' => $item['display_order']]);
        }

        return response()->json(['message' => 'Reordered successfully']);
    }
}
