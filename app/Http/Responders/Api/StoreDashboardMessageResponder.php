<?php

namespace App\Http\Responders\Api;

use App\Http\Requests\StoreDashboardMessageRequest;
use App\Http\Resources\DashboardMessageResource;
use App\Models\PersonalAccessToken;
use Illuminate\Http\JsonResponse;

class StoreDashboardMessageResponder
{
    public function __invoke(StoreDashboardMessageRequest $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        $message = $request->user()->dashboardMessages()->create([
            ...$request->validated(),
            'source' => $token instanceof PersonalAccessToken ? $token->name : null,
            'source_token_id' => $token instanceof PersonalAccessToken ? $token->id : null,
        ]);

        return (new DashboardMessageResource($message->fresh()))
            ->response()
            ->setStatusCode(201);
    }
}
