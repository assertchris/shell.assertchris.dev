<?php

namespace App\Models;

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
}
