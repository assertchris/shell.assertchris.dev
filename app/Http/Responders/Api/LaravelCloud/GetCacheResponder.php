<?php

namespace App\Http\Responders\Api\LaravelCloud;

use App\Http\Resources\LaravelCloud\CacheResource;
use App\Models\Account;
use App\Services\LaravelCloudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetCacheResponder
{
    public function __invoke(Request $request, Account $account, string $cache): CacheResource|JsonResponse
    {
        $data = (new LaravelCloudService($account))->getCache($cache);

        if (empty($data)) {
            abort(404);
        }

        return new CacheResource($data);
    }
}
