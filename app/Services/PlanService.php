<?php

namespace App\Services;

class PlanService
{
    protected array $plan;

    public function __construct()
    {
        $configPath = config_path('plan.php');

        if (file_exists($configPath)) {
            $this->plan = require $configPath;
        } else {
            // Self-hosted / dev: everything unlimited and enabled
            $this->plan = [
                'plan'             => 'enterprise',
                'max_members'      => 0,
                'max_forums'       => 0,
                'max_upload_mb'    => 0,
                'max_email_per_day' => 0,
                'custom_domain'    => true,
                'plugins_enabled'  => true,
                'custom_themes'    => true,
                'remove_branding'  => true,
                'backup_schedule'  => 'daily',
            ];
        }
    }

    public function planName(): string
    {
        return $this->plan['plan'] ?? 'enterprise';
    }

    public function maxMembers(): int
    {
        return (int) ($this->plan['max_members'] ?? 0);
    }

    public function maxForums(): int
    {
        return (int) ($this->plan['max_forums'] ?? 0);
    }

    public function maxUploadMb(): int
    {
        return (int) ($this->plan['max_upload_mb'] ?? 0);
    }

    public function maxEmailPerDay(): int
    {
        return (int) ($this->plan['max_email_per_day'] ?? 0);
    }

    public function customDomain(): bool
    {
        return (bool) ($this->plan['custom_domain'] ?? true);
    }

    public function pluginsEnabled(): bool
    {
        return (bool) ($this->plan['plugins_enabled'] ?? true);
    }

    public function customThemes(): bool
    {
        return (bool) ($this->plan['custom_themes'] ?? true);
    }

    public function removeBranding(): bool
    {
        return (bool) ($this->plan['remove_branding'] ?? true);
    }

    public function backupSchedule(): string
    {
        return $this->plan['backup_schedule'] ?? 'daily';
    }

    public function toArray(): array
    {
        return [
            'plan'              => $this->planName(),
            'max_members'       => $this->maxMembers(),
            'max_forums'        => $this->maxForums(),
            'max_upload_mb'     => $this->maxUploadMb(),
            'max_email_per_day' => $this->maxEmailPerDay(),
            'custom_domain'     => $this->customDomain(),
            'plugins_enabled'   => $this->pluginsEnabled(),
            'custom_themes'     => $this->customThemes(),
            'remove_branding'   => $this->removeBranding(),
            'backup_schedule'   => $this->backupSchedule(),
            'upgrade_url'       => 'https://billing.voltexahub.com',
        ];
    }
}
