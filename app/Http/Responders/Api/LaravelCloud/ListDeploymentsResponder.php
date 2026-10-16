<?php

namespace App\Http\Responders\Api\LaravelCloud;

use App\Http\Resources\LaravelCloud\DeploymentResource;
use App\Models\Account;
use App\Services\LaravelCloudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListDeploymentsResponder
{
    public function __invoke(Request $request, Account $account, string $environment): AnonymousResourceCollection|JsonResponse
    {
        $data = (new LaravelCloudService($account))->getDeployments($environment);

        return DeploymentResource::collection($data['data'] ?? []);
    }
}
