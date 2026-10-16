<?php

namespace App\Http\Responders\Api\LaravelCloud;

use App\Models\Account;
use App\Services\LaravelCloudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StartEnvironmentResponder
{
    public function __invoke(Request $request, Account $account, string $environment): JsonResponse
    {
        $data = (new LaravelCloudService($account))->startEnvironment($environment);

        return response()->json($data);
    }
}
