<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LockedContentUnlock extends Model
{
    protected $fillable = [
        'user_id',
        'content_hash',
        'credits_spent',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
