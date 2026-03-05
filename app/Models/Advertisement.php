<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Advertisement extends Model
{
    protected $fillable = [
        'title',
        'image_path',
        'url',
        'position',
        'is_active',
        'display_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
