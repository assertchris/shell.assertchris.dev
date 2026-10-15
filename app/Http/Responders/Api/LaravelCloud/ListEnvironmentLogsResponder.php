<?php

namespace App\Http\Responders\Api\LaravelCloud;

use App\Exceptions\LaravelCloud\LaravelCloudAuthException;
use App\Exceptions\LaravelCloud\LaravelCloudClientException;
use App\Exceptions\LaravelCloud\LaravelCloudRateLimitException;
use App\Http\Resources\LaravelCloud\LogResource;
use App\Models\Account;
use App\Services\LaravelCloudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListEnvironmentLogsResponder
{
    public function __invoke(Request $request, Account $account, string $environment): AnonymousResourceCollection|JsonResponse
    {
        $query = $request->only(['from', 'to', 'type', 'query', 'cursor']);

        try {
            $data = (new LaravelCloudService($account))->getEnvironmentLogs($environment, $query);
        } catch (LaravelCloudRateLimitException $e) {
            return response()->json(['error' => $e->getMessage()], 429, ['Retry-After' => $e->retryAfter]);
        } catch (LaravelCloudAuthException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        } catch (LaravelCloudClientException $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        return LogResource::collection($data['data'] ?? []);
    }
}
