<?php

namespace App\Observers;

use App\Models\Account;
use App\Support\CacheKey;

class AccountObserver
{
    public function saved(Account $account): void
    {
        CacheKey::for('account', $account->id)->forget();
        CacheKey::for('accounts', 'user', $account->user_id)->forget();

        $originalUserId = $account->getOriginal('user_id');

        if ($originalUserId && $originalUserId !== $account->user_id) {
            CacheKey::for('accounts', 'user', $originalUserId)->forget();
        }
    }

    public function deleted(Account $account): void
    {
        CacheKey::for('account', $account->id)->forget();
        CacheKey::for('accounts', 'user', $account->user_id)->forget();
    }
}
