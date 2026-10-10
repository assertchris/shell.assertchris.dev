<?php

namespace App\Observers;

use App\Models\User;
use App\Support\CacheKey;

class UserObserver
{
    public function saved(User $user): void
    {
        CacheKey::for('user', $user->id)->forget();
    }

    public function deleted(User $user): void
    {
        CacheKey::for('user', $user->id)->forget();
    }
}
