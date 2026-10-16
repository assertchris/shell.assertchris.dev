<?php

namespace App\Http\Responders\Api\LaravelCloud;

use App\Http\Resources\LaravelCloud\LogResource;
use App\Models\Account;
use App\Services\LaravelCloudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GetDeploymentLogsResponder
{
    public function __invoke(Request $request, Account $account, string $deployment): AnonymousResourceCollection|JsonResponse
    {
        $data = (new LaravelCloudService($account))->getDeploymentLogs($deployment);

        return LogResource::collection($data['data'] ?? []);
    }
}
