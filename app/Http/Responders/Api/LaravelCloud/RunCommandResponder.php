<?php

namespace App\Http\Responders\Api\LaravelCloud;

use App\Http\Resources\LaravelCloud\CommandResource;
use App\Models\Account;
use App\Services\LaravelCloudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RunCommandResponder
{
    public function __invoke(Request $request, Account $account, string $environment): CommandResource|JsonResponse
    {
        $validated = $request->validate([
            'command' => ['required', 'string'],
        ]);

        $data = (new LaravelCloudService($account))->runCommand($environment, $validated);

        return new CommandResource($data);
    }
}
