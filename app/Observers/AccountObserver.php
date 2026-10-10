<?php

namespace App\Observers;

use AC\LaravelTaggedCache\TaggedCache;
use App\Models\Account;

class AccountObserver
{
    public function saved(Account $account): void
    {
        TaggedCache::for(tags: ["account:{$account->id}"])->flush();
        TaggedCache::for(tags: ["account:user:{$account->user_id}"])->flush();

        $originalUserId = $account->getOriginal('user_id');

        if ($originalUserId && $originalUserId !== $account->user_id) {
            TaggedCache::for(tags: ["account:user:{$originalUserId}"])->flush();
        }
    }

    public function deleted(Account $account): void
    {
        TaggedCache::for(tags: ["account:{$account->id}"])->flush();
        TaggedCache::for(tags: ["account:user:{$account->user_id}"])->flush();
    }
}
