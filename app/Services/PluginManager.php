<?php

namespace App\Services;

use App\Models\InstalledPlugin;
use App\Plugins\Plugin;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class PluginManager
{
    protected array $loaded = [];

    /**
     * Get the path to the plugins directory.
     */
    public function pluginsPath(string $sub = ''): string
    {
        return base_path('plugins' . ($sub ? '/' . $sub : ''));
    }

    /**
     * Load a plugin class by slug and return the instance.
     */
    public function loadPlugin(string $slug): ?Plugin
    {
        if (isset($this->loaded[$slug])) {
            return $this->loaded[$slug];
        }

        $dir = $this->pluginsPath($slug);
        $className = Str::studly($slug) . 'Plugin';
        $classFile = $dir . '/' . $className . '.php';

        if (! File::exists($classFile)) {
            return null;
        }

        require_once $classFile;

        if (! class_exists($className)) {
            return null;
        }

        $instance = new $className();

        if (! $instance instanceof Plugin) {
            return null;
        }

        $this->loaded[$slug] = $instance;

        return $instance;
    }

    /**
     * Boot all enabled plugins — called from AppServiceProvider.
     */
    public function bootEnabled(): void
    {
        try {
            $plugins = InstalledPlugin::where('enabled', true)->get();
        } catch (\Throwable) {
            return; // Table may not exist yet
        }

        foreach ($plugins as $record) {
            try {
                $instance = $this->loadPlugin($record->slug);
                if ($instance) {
                    $instance->register();
                    $instance->boot();
                }
            } catch (\Throwable) {
                // Don't let a broken plugin crash the app
            }
        }
    }

    /**
     * Install a plugin from disk by slug.
     */
    public function install(string $slug): InstalledPlugin
    {
        $json = $this->readPluginJson($slug);

        if (! $json) {
            throw new \RuntimeException("Plugin [{$slug}] not found on disk.");
        }

        // Run plugin migrations if present
        $this->runPluginMigrations($slug);

        return InstalledPlugin::updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $json['name'] ?? $slug,
                'version' => $json['version'] ?? '0.0.0',
                'author' => $json['author'] ?? 'Unknown',
                'description' => $json['description'] ?? '',
                'installed_at' => now(),
            ]
        );
    }

    /**
     * Enable a plugin.
     */
    public function enable(string $slug): InstalledPlugin
    {
        $record = InstalledPlugin::where('slug', $slug)->firstOrFail();
        $record->update(['enabled' => true]);

        // Boot the plugin immediately
        $instance = $this->loadPlugin($slug);
        if ($instance) {
            $instance->register();
            $instance->boot();
        }

        return $record->fresh();
    }

    /**
     * Disable a plugin.
     */
    public function disable(string $slug): InstalledPlugin
    {
        $record = InstalledPlugin::where('slug', $slug)->firstOrFail();
        $record->update(['enabled' => false]);

        return $record->fresh();
    }

    /**
     * Uninstall a plugin.
     */
    public function uninstall(string $slug): void
    {
        InstalledPlugin::where('slug', $slug)->delete();
    }

    /**
     * Get all plugins — merge disk discovery with DB status.
     */
    public function getAll(): array
    {
        $installed = [];
        try {
            $installed = InstalledPlugin::all()->keyBy('slug')->toArray();
        } catch (\Throwable) {
            // Table may not exist yet
        }

        $plugins = [];
        $dir = $this->pluginsPath();

        if (! File::isDirectory($dir)) {
            return array_values($installed);
        }

        foreach (File::directories($dir) as $pluginDir) {
            $slug = basename($pluginDir);
            $json = $this->readPluginJson($slug);

            if (! $json) {
                continue;
            }

            $dbRecord = $installed[$slug] ?? null;

            $plugins[] = [
                'slug' => $slug,
                'name' => $json['name'] ?? $slug,
                'version' => $json['version'] ?? '0.0.0',
                'author' => $json['author'] ?? 'Unknown',
                'description' => $json['description'] ?? '',
                'installed' => $dbRecord !== null,
                'enabled' => $dbRecord['enabled'] ?? false,
                'installed_at' => $dbRecord['installed_at'] ?? null,
            ];

            unset($installed[$slug]);
        }

        // Include any DB records whose folder was removed (orphans)
        foreach ($installed as $slug => $record) {
            $plugins[] = [
                'slug' => $slug,
                'name' => $record['name'],
                'version' => $record['version'],
                'author' => $record['author'],
                'description' => $record['description'],
                'installed' => true,
                'enabled' => $record['enabled'],
                'installed_at' => $record['installed_at'],
                'missing_files' => true,
            ];
        }

        return $plugins;
    }

    /**
     * Read and decode a plugin's plugin.json.
     */
    protected function readPluginJson(string $slug): ?array
    {
        $path = $this->pluginsPath($slug . '/plugin.json');

        if (! File::exists($path)) {
            return null;
        }

        $json = json_decode(File::get($path), true);

        return is_array($json) ? $json : null;
    }

    /**
     * Run migrations from a plugin's migrations/ directory.
     */
    protected function runPluginMigrations(string $slug): void
    {
        $migrationsPath = $this->pluginsPath($slug . '/migrations');

        if (File::isDirectory($migrationsPath)) {
            Artisan::call('migrate', [
                '--path' => 'plugins/' . $slug . '/migrations',
                '--force' => true,
            ]);
        }
    }
}
