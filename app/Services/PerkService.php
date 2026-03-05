<?php

namespace App\Services;

use App\Models\UpgradePlan;
use Illuminate\Support\Facades\Cache;

class PerkService
{
    const NO_ADS          = 'no_ads';
    const BYPASS_UNLOCK   = 'bypass_unlock';
    const PROFILE_COVER   = 'profile_cover';
    const CUSTOM_CSS      = 'custom_css';
    const LOCKED_BYPASS   = 'locked_content_bypass';
    const CHANGE_USERNAME = 'change_username';
    const USERBAR_HUE     = 'userbar_hue';
    const USERNAME_COLOR  = 'username_color';
    const AWARDS_REORDER  = 'awards_reorder';
    const PRE_ACCESS      = 'pre_access';

    public function userHasPerk($user, string $type): bool
    {
        if (!$user) return false;
        if ($user->hasRole('admin')) return true;

        $userRoles = $user->getRoleNames()->toArray();

        $plans = Cache::remember('upgrade_plans_features', 300, fn () =>
            UpgradePlan::where('is_active', true)->get(['role_name', 'features'])
        );

        foreach ($plans as $plan) {
            if (!$plan->role_name || !in_array($plan->role_name, $userRoles)) continue;
            foreach (($plan->features ?? []) as $f) {
                if (($f['type'] ?? '') === $type) return true;
            }
        }

        return false;
    }
}
