<?php

namespace App\Plugins;

abstract class Plugin
{
    abstract public function slug(): string;
    abstract public function name(): string;
    abstract public function version(): string;
    abstract public function description(): string;
    abstract public function author(): string;

    public function register(): void {}
    public function boot(): void {}

    public function adminMenuItems(): array
    {
        return [];
    }
}
