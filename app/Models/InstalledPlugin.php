<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstalledPlugin extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'version',
        'author',
        'description',
        'enabled',
        'installed_at',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'installed_at' => 'datetime',
    ];
}
