<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vlf\Matter;

class MatterPolicy
{
    /** Staff see every matter; a client only their own organisation's matters. */
    public function view(User $user, Matter $matter): bool
    {
        return $user->isStaff() || ($user->client_id !== null && $matter->client_id === $user->client_id);
    }

    public function update(User $user, Matter $matter): bool
    {
        return $user->isStaff();
    }
}
