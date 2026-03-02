<?php

namespace App\Notifications;

use App\Models\Award;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AwardReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Award $award,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'award_received',
            'title' => 'Award received!',
            'body' => 'You received the "' . $this->award->name . '" award',
            'url' => '/profile',
            'icon' => 'award',
            'created_at' => now()->toISOString(),
        ];
    }
}
