<?php

namespace App\Observers;

use AC\LaravelTaggedCache\TaggedCache;
use App\Models\PersonalAccessToken;

class PersonalAccessTokenObserver
{
    /** @var list<string> */
    private array $securityFields = ['token', 'name', 'abilities', 'expires_at', 'last_ip_address', 'ip_changed_at'];

    public function saving(PersonalAccessToken $token): bool
    {
        $dirty = array_keys($token->getDirty());

        $hasSecurityChange = array_intersect($dirty, $this->securityFields) !== [];

        if (! $hasSecurityChange && in_array('last_used_at', $dirty, true)) {
            $hash = $token->token;
            $cache = TaggedCache::for(tags: ['tokens'], context: ['hash' => $hash]);
            $cached = $cache->get();

            if ($cached !== null) {
                $cached->last_used_at = $token->last_used_at;
                $cache->put($cached, config('cache.ttl.models'));
            }

            return false;
        }

        return true;
    }

    public function saved(PersonalAccessToken $token): void
    {
        TaggedCache::for(tags: ['tokens'], context: ['hash' => $token->token])->forget();
    }

    public function deleted(PersonalAccessToken $token): void
    {
        TaggedCache::for(tags: ['tokens'], context: ['hash' => $token->token])->forget();
    }
}
