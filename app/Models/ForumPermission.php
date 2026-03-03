<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForumPermission extends Model
{
    protected $fillable = ['forum_id', 'role_name', 'can_view', 'can_post', 'can_reply'];

    protected $casts = [
        'can_view'  => 'boolean',
        'can_post'  => 'boolean',
        'can_reply' => 'boolean',
    ];

    public function forum()
    {
        return $this->belongsTo(Forum::class);
    }
}
