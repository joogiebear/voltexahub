<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'thread_id', 'user_id', 'body', 'is_first_post', 'reaction_count',
        'edited_at', 'edit_count',
    ];

    protected $appends = ['is_edited'];

    protected function casts(): array
    {
        return [
            'is_first_post' => 'boolean',
            'edited_at' => 'datetime',
        ];
    }

    protected function isEdited(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::get(
            fn () => $this->edited_at !== null
        );
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(Reaction::class);
    }
}
