<?php

namespace App\Http\Responders\Api\LaravelCloud;

use App\Http\Resources\LaravelCloud\FailedJobResource;
use App\Models\Account;
use App\Services\LaravelCloudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListFailedJobsResponder
{
    public function __invoke(Request $request, Account $account, string $instance): AnonymousResourceCollection|JsonResponse
    {
        $data = (new LaravelCloudService($account))->getFailedJobs($instance);

        return FailedJobResource::collection($data['data'] ?? []);
    }
}
