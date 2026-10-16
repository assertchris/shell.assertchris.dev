<?php

namespace App\Http\Responders\Api\LaravelCloud;

use App\Http\Resources\LaravelCloud\MetricsResource;
use App\Models\Account;
use App\Services\LaravelCloudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetCacheMetricsResponder
{
    public function __invoke(Request $request, Account $account, string $cache): MetricsResource|JsonResponse
    {
        $query = $request->only(['period']);

        $data = (new LaravelCloudService($account))->getCacheMetrics($cache, $query);

        if (empty($data)) {
            abort(404);
        }

        return new MetricsResource($data);
    }
}
