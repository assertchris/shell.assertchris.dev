<?php

namespace App\Http\Responders\Api\LaravelCloud;

use App\Http\Resources\LaravelCloud\EnvironmentResource;
use App\Models\Account;
use App\Services\LaravelCloudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetEnvironmentResponder
{
    public function __invoke(Request $request, Account $account, string $environment): EnvironmentResource|JsonResponse
    {
        $data = (new LaravelCloudService($account))->getEnvironment($environment);

        if (empty($data)) {
            abort(404);
        }

        return new EnvironmentResource($data);
    }
}
