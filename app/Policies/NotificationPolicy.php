<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vlf\Notification;

class NotificationPolicy
{
    /** Only the person a notification was sent to can open or mark it. */
    public function update(User $user, Notification $notification): bool
    {
        return $notification->recipient === $user->name;
    }
}
