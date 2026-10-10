<?php

namespace App\Observers;

use AC\LaravelTaggedCache\TaggedCache;
use App\Models\User;

class UserObserver
{
    public function saved(User $user): void
    {
        TaggedCache::for(tags: ["user:{$user->id}"])->flush();
    }

    public function deleted(User $user): void
    {
        TaggedCache::for(tags: ["user:{$user->id}"])->flush();
    }
}
