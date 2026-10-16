<?php

namespace App\Http\Responders\Api\LaravelCloud;

use App\Http\Resources\LaravelCloud\EnvironmentResource;
use App\Models\Account;
use App\Services\LaravelCloudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListEnvironmentsResponder
{
    public function __invoke(Request $request, Account $account, string $application): AnonymousResourceCollection|JsonResponse
    {
        $data = (new LaravelCloudService($account))->getEnvironments($application);

        return EnvironmentResource::collection($data['data'] ?? []);
    }
}
