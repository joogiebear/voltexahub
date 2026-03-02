<?php

namespace App\Notifications;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DMReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Message $message,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'dm_received',
            'title' => 'New message',
            'body' => $this->message->sender->username . ' sent you a message',
            'url' => '/messages/' . $this->message->conversation_id,
            'icon' => 'mail',
            'created_at' => now()->toISOString(),
        ];
    }
}
