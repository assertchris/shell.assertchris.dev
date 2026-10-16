<?php

namespace App\Http\Responders\Api\LaravelCloud;

use App\Models\Account;
use App\Services\LaravelCloudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResumeQueueResponder
{
    public function __invoke(Request $request, Account $account, string $instance): JsonResponse
    {
        $data = (new LaravelCloudService($account))->resumeQueue($instance);

        return response()->json($data);
    }
}
