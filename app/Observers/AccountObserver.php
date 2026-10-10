<?php

namespace App\Observers;

use App\Models\Account;
use App\Support\CacheKey;
use Illuminate\Support\Facades\Cache;

class AccountObserver
{
    public function saved(Account $account): void
    {
        Cache::forget(CacheKey::for('account', $account->id));
        Cache::forget(CacheKey::for('accounts', 'user', $account->user_id));

        $originalUserId = $account->getOriginal('user_id');

        if ($originalUserId && $originalUserId !== $account->user_id) {
            Cache::forget(CacheKey::for('accounts', 'user', $originalUserId));
        }
    }

    public function deleted(Account $account): void
    {
        Cache::forget(CacheKey::for('account', $account->id));
        Cache::forget(CacheKey::for('accounts', 'user', $account->user_id));
    }
}
