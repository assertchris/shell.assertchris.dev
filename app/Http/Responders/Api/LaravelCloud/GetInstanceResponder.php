<?php

namespace App\Http\Responders\Api\LaravelCloud;

use App\Http\Resources\LaravelCloud\InstanceResource;
use App\Models\Account;
use App\Services\LaravelCloudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetInstanceResponder
{
    public function __invoke(Request $request, Account $account, string $instance): InstanceResource|JsonResponse
    {
        $data = (new LaravelCloudService($account))->getInstance($instance);

        if (empty($data)) {
            abort(404);
        }

        return new InstanceResource($data);
    }
}
