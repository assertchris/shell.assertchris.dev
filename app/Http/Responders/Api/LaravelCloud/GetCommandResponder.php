<?php

namespace App\Http\Responders\Api\LaravelCloud;

use App\Http\Resources\LaravelCloud\CommandResource;
use App\Models\Account;
use App\Services\LaravelCloudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetCommandResponder
{
    public function __invoke(Request $request, Account $account, string $command): CommandResource|JsonResponse
    {
        $data = (new LaravelCloudService($account))->getCommand($command);

        if (empty($data)) {
            abort(404);
        }

        return new CommandResource($data);
    }
}
