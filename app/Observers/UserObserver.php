<?php

namespace App\Observers;

use App\Models\User;
use App\Support\CacheKey;
use Illuminate\Support\Facades\Cache;

class UserObserver
{
    public function saved(User $user): void
    {
        Cache::forget(CacheKey::for('user', $user->id));
    }

    public function deleted(User $user): void
    {
        Cache::forget(CacheKey::for('user', $user->id));
    }
}
