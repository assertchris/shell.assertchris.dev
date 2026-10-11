<?php

namespace App\Http\Responders\Api;

use AC\LaravelTaggedCache\TaggedCache;
use App\Http\Resources\AccountResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListAccountsResponder
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $userId = $request->user()->id;

        $accounts = TaggedCache::for(
            tags: ['accounts', "account:user:{$userId}"],
            context: ['userId' => $userId],
        )->remember(config('cache.ttl.models'), fn () => $request->user()->accounts()->where('is_active', true)->get());

        return AccountResource::collection($accounts);
    }
}
