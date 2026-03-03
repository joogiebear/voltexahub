<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Thread extends Model
{
    protected $fillable = [
        'forum_id', 'subforum_id', 'user_id', 'title', 'slug', 'body',
        'is_pinned', 'is_locked', 'is_solved', 'view_count', 'reply_count',
        'last_reply_at', 'last_reply_user_id',
    ];

    protected $appends = ['author'];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'is_locked' => 'boolean',
            'is_solved' => 'boolean',
            'last_reply_at' => 'datetime',
        ];
    }

    public function forum(): BelongsTo
    {
        return $this->belongsTo(Forum::class);
    }

    public function subforum(): BelongsTo
    {
        return $this->belongsTo(Subforum::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getAuthorAttribute()
    {
        return $this->relationLoaded('user') ? $this->user : null;
    }

    public function lastReplyUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_reply_user_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
