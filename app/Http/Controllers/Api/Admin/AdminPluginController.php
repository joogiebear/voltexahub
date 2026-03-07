<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\PlanService;
use App\Services\PluginManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPluginController extends Controller
{
    public function __construct(
        protected PluginManager $pluginManager
    ) {}

    /**
     * List all plugins (from disk + DB status merged).
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->pluginManager->getAll(),
        ]);
    }

    /**
     * Install a plugin from disk by slug.
     */
    public function install(Request $request): JsonResponse
    {
        if (! app(PlanService::class)->pluginsEnabled()) {
            return response()->json([
                'error'       => 'plugins_not_available',
                'upgrade_url' => 'https://billing.voltexahub.com',
            ], 403);
        }

        $request->validate([
            'slug' => 'required|string|max:255',
        ]);

        $record = $this->pluginManager->install($request->slug);

        return response()->json([
            'data' => $record,
            'message' => 'Plugin installed successfully.',
        ]);
    }

    /**
     * Toggle a plugin enabled/disabled.
     */
    public function toggle(string $slug): JsonResponse
    {
        $record = \App\Models\InstalledPlugin::where('slug', $slug)->firstOrFail();

        if ($record->enabled) {
            $record = $this->pluginManager->disable($slug);
        } else {
            $record = $this->pluginManager->enable($slug);
        }

        return response()->json([
            'data' => $record,
            'message' => $record->enabled ? 'Plugin enabled.' : 'Plugin disabled.',
        ]);
    }

    /**
     * Uninstall a plugin.
     */
    public function uninstall(string $slug): JsonResponse
    {
        $this->pluginManager->uninstall($slug);

        return response()->json([
            'message' => 'Plugin uninstalled.',
        ]);
    }
}
