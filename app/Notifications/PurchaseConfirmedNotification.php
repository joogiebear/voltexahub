<?php

namespace App\Notifications;

use App\Models\StorePurchase;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PurchaseConfirmedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public StorePurchase $purchase,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'purchase_confirmed',
            'title' => 'Purchase confirmed',
            'body' => 'Your purchase of "' . $this->purchase->storeItem->name . '" was successful',
            'url' => '/store',
            'icon' => 'shopping-bag',
            'created_at' => now()->toISOString(),
        ];
    }
}
