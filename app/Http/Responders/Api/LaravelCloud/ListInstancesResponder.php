<?php

namespace App\Http\Responders\Api\LaravelCloud;

use App\Http\Resources\LaravelCloud\InstanceResource;
use App\Models\Account;
use App\Services\LaravelCloudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListInstancesResponder
{
    public function __invoke(Request $request, Account $account, string $environment): AnonymousResourceCollection|JsonResponse
    {
        $data = (new LaravelCloudService($account))->getInstances($environment);

        return InstanceResource::collection($data['data'] ?? []);
    }
}
