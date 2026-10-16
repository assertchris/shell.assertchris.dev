<?php

namespace App\Http\Responders\Api\LaravelCloud;

use App\Http\Resources\LaravelCloud\ApplicationResource;
use App\Models\Account;
use App\Services\LaravelCloudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListApplicationsResponder
{
    public function __invoke(Request $request, Account $account): AnonymousResourceCollection|JsonResponse
    {
        $data = (new LaravelCloudService($account))->getApplications();

        return ApplicationResource::collection($data['data'] ?? []);
    }
}
