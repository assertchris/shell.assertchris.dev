<?php

namespace App\Http\Responders\Api;

use App\Http\Resources\AccountResource;
use App\Support\CacheKey;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;

class ListAccountsResponder
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $userId = $request->user()->id;

        $accounts = Cache::remember(
            CacheKey::for('accounts', 'user', $userId),
            now()->addDays(7),
            fn () => $request->user()->accounts()->where('is_active', true)->get(),
        );

        return AccountResource::collection($accounts);
    }
}
