<?php

namespace App\Http\Responders\Api;

use App\Http\Resources\AccountResource;
use App\Support\CacheKey;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListAccountsResponder
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $userId = $request->user()->id;

        $accounts = CacheKey::for('accounts', 'user', $userId)
            ->remember(now()->addDays(7), fn () => $request->user()->accounts()->where('is_active', true)->get());

        return AccountResource::collection($accounts);
    }
}
