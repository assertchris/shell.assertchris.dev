<?php

namespace App\Http\Responders\Api\LaravelCloud;

use App\Http\Requests\LaravelCloud\ListEnvironmentLogsRequest;
use App\Http\Resources\LaravelCloud\LogResource;
use App\Models\Account;
use App\Services\LaravelCloudService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListEnvironmentLogsResponder
{
    public function __invoke(ListEnvironmentLogsRequest $request, Account $account, string $environment): AnonymousResourceCollection
    {
        $query = $request->only(['from', 'to', 'type', 'query', 'cursor']);

        $data = (new LaravelCloudService($account))->getEnvironmentLogs($environment, $query);

        return LogResource::collection($data['data'] ?? []);
    }
}
