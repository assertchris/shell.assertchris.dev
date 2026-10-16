<?php

namespace App\Http\Responders\Api\LaravelCloud;

use App\Http\Resources\LaravelCloud\DeploymentResource;
use App\Models\Account;
use App\Services\LaravelCloudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetDeploymentResponder
{
    public function __invoke(Request $request, Account $account, string $deployment): DeploymentResource|JsonResponse
    {
        $data = (new LaravelCloudService($account))->getDeployment($deployment);

        if (empty($data)) {
            abort(404);
        }

        return new DeploymentResource($data);
    }
}
