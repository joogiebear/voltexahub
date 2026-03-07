<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Forum;
use App\Models\Game;
use App\Services\PlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminForumController extends Controller
{
    /**
     * Flat list of all forums with parent/category info.
     */
    public function index(): JsonResponse
    {
        $forums = Forum::with(['category:id,name', 'parentForum:id,name'])
            ->orderBy('category_id')
            ->orderBy('parent_forum_id')
            ->orderBy('display_order')
            ->get()
            ->map(fn ($f) => [
                'id' => $f->id,
                'name' => $f->name,
                'slug' => $f->slug,
                'description' => $f->description,
                'icon' => $f->icon,
                'category_id' => $f->category_id,
                'category_name' => $f->category?->name,
                'parent_forum_id' => $f->parent_forum_id,
                'parent_forum_name' => $f->parentForum?->name,
                'display_order' => $f->display_order,
                'is_active' => $f->is_active,
            ]);

        return response()->json(['data' => $forums]);
    }

    /**
     * Structured tree: categories → top-level forums → subforums.
     */
    public function tree(): JsonResponse
    {
        $categories = Category::with([
            'forums' => fn ($q) => $q->whereNull('parent_forum_id')
                ->orderBy('display_order')
                ->with([
                    'subforums' => fn ($q) => $q->orderBy('display_order'),
                    'lastPostUser:id,username',
                ]),
        ])->orderBy('display_order')->get();

        return response()->json(['data' => $categories]);
    }

    // ── Game CRUD (kept for backwards compat, games table not dropped) ──

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

    // ── Category CRUD ──

    public function createCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'game_id' => ['nullable', 'exists:games,id'],
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
            'data' => $category,
            'message' => 'Category created successfully.',
        ], 201);
    }

    public function updateCategory(Request $request, int $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        $validated = $request->validate([
            'game_id' => ['nullable', 'exists:games,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', 'unique:categories,slug,' . $id],
            'description' => ['nullable', 'string'],
            'display_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $category->update($validated);

        return response()->json([
            'data' => $category->fresh(),
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

    // ── Forum CRUD ──

    public function createForum(Request $request): JsonResponse
    {
        $plan = app(PlanService::class);
        $max = $plan->maxForums();

        if ($max > 0 && Forum::count() >= $max) {
            return response()->json([
                'error'       => 'forum_limit_reached',
                'limit'       => $max,
                'upgrade_url' => 'https://billing.voltexahub.com',
            ], 403);
        }

        $validated = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'parent_forum_id' => ['nullable', 'exists:forums,id'],
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
            'data' => $forum->load(['category', 'parentForum']),
            'message' => 'Forum created successfully.',
        ], 201);
    }

    public function updateForum(Request $request, int $id): JsonResponse
    {
        $forum = Forum::findOrFail($id);

        $validated = $request->validate([
            'category_id' => ['sometimes', 'exists:categories,id'],
            'parent_forum_id' => ['nullable', 'exists:forums,id'],
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
            'data' => $forum->fresh()->load(['category', 'parentForum']),
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

    // ── Reordering ──

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
