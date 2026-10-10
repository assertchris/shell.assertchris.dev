<?php

namespace App\Models;

use App\Support\CacheKey;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    /** @var array<int, string> */
    protected $fillable = [
        'name',
        'token',
        'abilities',
        'expires_at',
        'last_ip_address',
        'ip_changed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'ip_changed_at' => 'datetime',
        ];
    }

    public static function findToken($token): ?static
    {
        $rawToken = str_contains($token, '|') ? explode('|', $token, 2)[1] : $token;
        $hash = hash('sha256', $rawToken);

        $cachedToken = Cache::remember(
            CacheKey::for('token', $hash),
            now()->addDays(7),
            fn () => parent::findToken($token),
        );

        if ($cachedToken) {
            $cachedToken->setRelation('tokenable', User::findCached($cachedToken->tokenable_id));
        }

        return $cachedToken;
    }
}
