<?php

namespace App\Http\Responders\Api\LaravelCloud;

use App\Exceptions\LaravelCloud\LaravelCloudAuthException;
use App\Exceptions\LaravelCloud\LaravelCloudClientException;
use App\Exceptions\LaravelCloud\LaravelCloudRateLimitException;
use App\Http\Resources\LaravelCloud\MetricsResource;
use App\Models\Account;
use App\Services\LaravelCloudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetDatabaseMetricsResponder
{
    public function __invoke(Request $request, Account $account, string $database): MetricsResource|JsonResponse
    {
        $query = $request->only(['period']);

        try {
            $data = (new LaravelCloudService($account))->getDatabaseMetrics($database, $query);
        } catch (LaravelCloudRateLimitException $e) {
            return response()->json(['error' => $e->getMessage()], 429, ['Retry-After' => $e->retryAfter]);
        } catch (LaravelCloudAuthException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        } catch (LaravelCloudClientException $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        if (empty($data)) {
            abort(404);
        }

        return new MetricsResource($data);
    }
}
