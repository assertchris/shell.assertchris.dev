<?php

namespace App\Observers;

use App\Models\PersonalAccessToken;
use App\Support\CacheKey;
use Illuminate\Support\Facades\Cache;

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
            $cached = Cache::get(CacheKey::for('token', $hash));

            if ($cached) {
                $cached->last_used_at = $token->last_used_at;
                Cache::put(CacheKey::for('token', $hash), $cached, now()->addDays(7));
            }

            return false;
        }

        return true;
    }

    public function saved(PersonalAccessToken $token): void
    {
        Cache::forget(CacheKey::for('token', $token->token));
    }

    public function deleted(PersonalAccessToken $token): void
    {
        Cache::forget(CacheKey::for('token', $token->token));
    }
}
