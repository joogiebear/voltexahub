<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{userId}', function ($user, $userId) {
    return $user->id === (int) $userId;
});

Broadcast::channel('online', function ($user) {
    return ['id' => $user->id, 'username' => $user->username];
});
