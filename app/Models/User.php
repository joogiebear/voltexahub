<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Events\NewNotification;
use App\Notifications\AchievementUnlockedNotification;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'username',
        'user_title',
        'bio',
        'signature',
        'avatar_color',
        'credits',
        'post_count',
        'is_online',
        'last_active_at',
        'discord_username',
        'twitter_handle',
        'website_url',
        'minecraft_ign',
        'minecraft_verified',
        'rust_steam_id',
        'rust_verified',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_online' => 'boolean',
            'last_active_at' => 'datetime',
            'minecraft_verified' => 'boolean',
            'rust_verified' => 'boolean',
            'credits' => 'integer',
            'post_count' => 'integer',
        ];
    }

    public function threads(): HasMany
    {
        return $this->hasMany(Thread::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function userAwards(): HasMany
    {
        return $this->hasMany(UserAward::class);
    }

    public function userAchievements(): HasMany
    {
        return $this->hasMany(UserAchievement::class);
    }

    public function creditsLog(): HasMany
    {
        return $this->hasMany(CreditsLog::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(StorePurchase::class);
    }

    public function cosmetics(): HasMany
    {
        return $this->hasMany(UserCosmetic::class);
    }

    public function activeCosmetic(): HasOne
    {
        return $this->hasOne(UserCosmetic::class)->where('is_active', true);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(UserSession::class);
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_user')
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    public function addCredits(int $amount, string $reason, ?string $referenceType = null, ?int $referenceId = null): void
    {
        $this->increment('credits', $amount);

        CreditsLog::create([
            'user_id' => $this->id,
            'amount' => $amount,
            'balance_after' => $this->fresh()->credits,
            'reason' => $reason,
            'type' => 'earn',
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);
    }

    public function spendCredits(int $amount, string $reason, ?string $referenceType = null, ?int $referenceId = null): bool
    {
        if ($this->credits < $amount) {
            return false;
        }

        $this->decrement('credits', $amount);

        CreditsLog::create([
            'user_id' => $this->id,
            'amount' => -$amount,
            'balance_after' => $this->fresh()->credits,
            'reason' => $reason,
            'type' => 'spend',
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);

        return true;
    }

    public function checkAchievements(): void
    {
        $achievements = Achievement::all();

        foreach ($achievements as $achievement) {
            $userAchievement = UserAchievement::firstOrCreate(
                ['user_id' => $this->id, 'achievement_id' => $achievement->id],
                ['progress' => 0]
            );

            if ($userAchievement->unlocked_at) {
                continue;
            }

            $progress = match ($achievement->trigger_key) {
                'post_count' => $this->post_count,
                'thread_count' => $this->threads()->count(),
                'reactions_received' => $this->posts()->sum('reaction_count'),
                'account_age_days' => $this->created_at ? (int) $this->created_at->diffInDays(now()) : 0,
                'purchases' => $this->purchases()->where('status', 'completed')->count(),
                'credits_spent' => abs($this->creditsLog()->where('type', 'spend')->sum('amount')),
                'solutions' => $this->posts()
                    ->whereHas('thread', fn ($q) => $q->where('is_solved', true))
                    ->where('is_first_post', false)
                    ->count(),
                default => 0,
            };

            $userAchievement->progress = (int) ($progress ?? 0);

            if ($progress >= $achievement->trigger_value) {
                $userAchievement->unlocked_at = now();
                if ($achievement->credits_reward > 0) {
                    $this->addCredits($achievement->credits_reward, "Achievement: {$achievement->name}");
                }
                $this->notify(new AchievementUnlockedNotification($achievement));
                broadcast(new NewNotification($this->id, [
                    'type' => 'achievement_unlocked',
                    'title' => 'Achievement unlocked!',
                    'body' => 'You unlocked "' . $achievement->name . '"',
                    'url' => '/achievements',
                ]));
            }

            $userAchievement->save();
        }
    }
}
