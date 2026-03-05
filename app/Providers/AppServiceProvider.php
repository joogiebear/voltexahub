<?php

namespace App\Providers;

use App\Services\PerkService;
use App\Services\PluginManager;
use App\Services\TextFormatterService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PerkService::class);
        $this->app->singleton(PluginManager::class);
        $this->app->singleton(TextFormatterService::class);
    }

    public function boot(): void
    {
        // Apply mail config from forum_config table at runtime
        // This allows mail settings to be configured via the admin panel
        try {
            $mailer   = \App\Models\ForumConfig::get('mail_mailer', null);
            $host     = \App\Models\ForumConfig::get('mail_host', null);
            $port     = \App\Models\ForumConfig::get('mail_port', null);
            $username = \App\Models\ForumConfig::get('mail_username', null);
            $password = \App\Models\ForumConfig::get('mail_password', null);
            $encrypt  = \App\Models\ForumConfig::get('mail_encryption', 'tls');
            $from     = \App\Models\ForumConfig::get('mail_from_address', null);
            $name     = \App\Models\ForumConfig::get('mail_from_name', config('app.name'));

            if ($host && $username) {
                Config::set('mail.default', $mailer ?: 'smtp');
                Config::set('mail.mailers.smtp.host', $host);
                Config::set('mail.mailers.smtp.port', (int) ($port ?: 587));
                Config::set('mail.mailers.smtp.username', $username);
                Config::set('mail.mailers.smtp.password', $password);
                Config::set('mail.mailers.smtp.encryption', $encrypt);
                if ($from) {
                    Config::set('mail.from.address', $from);
                }
                Config::set('mail.from.name', $name);
            }
        } catch (\Throwable) {
            // DB may not be ready yet (first boot/migration) — skip silently
        }

        // Boot all enabled plugins
        app(PluginManager::class)->bootEnabled();
    }
}
