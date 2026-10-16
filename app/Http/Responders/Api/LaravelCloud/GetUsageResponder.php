<?php

namespace App\Http\Responders\Api\LaravelCloud;

use App\Http\Resources\LaravelCloud\UsageResource;
use App\Models\Account;
use App\Services\LaravelCloudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetUsageResponder
{
    public function __invoke(Request $request, Account $account): UsageResource|JsonResponse
    {
        $query = $request->only(['period', 'environment']);

        $data = (new LaravelCloudService($account))->getUsage($query);

        if (empty($data)) {
            abort(404);
        }

        return new UsageResource($data);
    }
}
