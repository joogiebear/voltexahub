<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UpgradePlan extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'color', 'price', 'term',
        'role_name', 'rep_power_pos', 'rep_power_neg', 'rep_daily_limit',
        'features', 'one_time_bonus', 'stripe_price_id',
        'display_order', 'is_active', 'is_featured', 'required_plan_id',
    ];

    public function requiredPlan()
    {
        return $this->belongsTo(UpgradePlan::class, 'required_plan_id');
    }

    protected $casts = [
        'features'        => 'array',
        'one_time_bonus'  => 'array',
        'price'           => 'float',
        'is_active'       => 'boolean',
        'is_featured'     => 'boolean',
        'rep_power_pos'   => 'integer',
        'rep_power_neg'   => 'integer',
        'rep_daily_limit' => 'integer',
    ];
}
