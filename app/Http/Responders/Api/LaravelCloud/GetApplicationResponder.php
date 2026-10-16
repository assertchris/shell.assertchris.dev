<?php

namespace App\Http\Responders\Api\LaravelCloud;

use App\Http\Resources\LaravelCloud\ApplicationResource;
use App\Models\Account;
use App\Services\LaravelCloudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetApplicationResponder
{
    public function __invoke(Request $request, Account $account, string $application): ApplicationResource|JsonResponse
    {
        $data = (new LaravelCloudService($account))->getApplication($application);

        if (empty($data)) {
            abort(404);
        }

        return new ApplicationResource($data);
    }
}
