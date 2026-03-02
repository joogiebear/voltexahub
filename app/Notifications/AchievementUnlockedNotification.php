<?php

namespace App\Notifications;

use App\Models\Achievement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AchievementUnlockedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Achievement $achievement,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'achievement_unlocked',
            'title' => 'Achievement unlocked!',
            'body' => 'You unlocked "' . $this->achievement->name . '"'
                . ($this->achievement->credits_reward > 0
                    ? ' (+' . $this->achievement->credits_reward . ' credits)'
                    : ''),
            'url' => '/achievements',
            'icon' => 'trophy',
            'created_at' => now()->toISOString(),
        ];
    }
}
