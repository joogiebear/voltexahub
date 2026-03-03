<?php

use App\Plugins\Plugin;

class AnnouncementsPlugin extends Plugin
{
    public function slug(): string
    {
        return 'announcements';
    }

    public function name(): string
    {
        return 'Announcements';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function description(): string
    {
        return 'Display site-wide announcement banners.';
    }

    public function author(): string
    {
        return 'VoltexaHub';
    }

    public function register(): void
    {
        \Illuminate\Support\Facades\Route::middleware(['api'])
            ->prefix('api')
            ->group(base_path('plugins/announcements/routes.php'));
    }

    public function boot(): void {}
}
