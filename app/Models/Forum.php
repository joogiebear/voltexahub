<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Forum extends Model
{
    protected $fillable = [
        'category_id', 'name', 'slug', 'description', 'icon',
        'display_order', 'is_active', 'thread_count', 'post_count',
        'last_post_at', 'last_post_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_post_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function threads(): HasMany
    {
        return $this->hasMany(Thread::class);
    }

    public function subforums(): HasMany
    {
        return $this->hasMany(Subforum::class);
    }

    public function lastPostUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_post_user_id');
    }

    public function forumPermissions()
    {
        return $this->hasMany(ForumPermission::class);
    }

    public function permissionFor(string $role): array
    {
        $perm = $this->forumPermissions()->where('role_name', $role)->first();
        if (!$perm) return ['can_view' => true, 'can_post' => true, 'can_reply' => true];
        return ['can_view' => $perm->can_view, 'can_post' => $perm->can_post, 'can_reply' => $perm->can_reply];
    }
}
