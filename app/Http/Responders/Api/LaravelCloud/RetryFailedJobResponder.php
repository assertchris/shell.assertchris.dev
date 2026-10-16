<?php

namespace App\Http\Responders\Api\LaravelCloud;

use App\Models\Account;
use App\Services\LaravelCloudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RetryFailedJobResponder
{
    public function __invoke(Request $request, Account $account, string $instance, string $job): JsonResponse
    {
        $data = (new LaravelCloudService($account))->retryFailedJob($instance, $job);

        return response()->json($data);
    }
}
