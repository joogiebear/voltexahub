<?php

namespace App\Notifications;

use App\Models\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MentionNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Post $post,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'mention',
            'title' => 'You were mentioned',
            'body' => $this->post->user->username . ' mentioned you in a post',
            'url' => '/threads/' . $this->post->thread_id,
            'icon' => 'at-sign',
            'created_at' => now()->toISOString(),
        ];
    }
}
