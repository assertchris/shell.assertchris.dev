<?php

namespace App\Http\Responders\Api\LaravelCloud;

use App\Http\Requests\LaravelCloud\GetDeploymentLogsRequest;
use App\Http\Resources\LaravelCloud\LogResource;
use App\Models\Account;
use App\Services\LaravelCloudService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GetDeploymentLogsResponder
{
    public function __invoke(GetDeploymentLogsRequest $request, Account $account, string $deployment): AnonymousResourceCollection
    {
        $query = $request->only(['from', 'to', 'cursor']);

        $data = (new LaravelCloudService($account))->getDeploymentLogs($deployment, $query);

        return LogResource::collection($data['data'] ?? []);
    }
}
