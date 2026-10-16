<?php

namespace App\Http\Responders\Api\LaravelCloud;

use App\Http\Resources\LaravelCloud\DatabaseResource;
use App\Models\Account;
use App\Services\LaravelCloudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetDatabaseResponder
{
    public function __invoke(Request $request, Account $account, string $database): DatabaseResource|JsonResponse
    {
        $data = (new LaravelCloudService($account))->getDatabase($database);

        if (empty($data)) {
            abort(404);
        }

        return new DatabaseResource($data);
    }
}
