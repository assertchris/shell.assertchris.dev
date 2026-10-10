<?php

namespace App\Observers;

use App\Models\PersonalAccessToken;
use App\Support\CacheKey;

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
            $cached = CacheKey::for('token', $hash)->get();

            if ($cached) {
                $cached->last_used_at = $token->last_used_at;
                CacheKey::for('token', $hash)->put($cached, now()->addDays(7));
            }

            return false;
        }

        return true;
    }

    public function saved(PersonalAccessToken $token): void
    {
        CacheKey::for('token', $token->token)->forget();
    }

    public function deleted(PersonalAccessToken $token): void
    {
        CacheKey::for('token', $token->token)->forget();
    }
}
