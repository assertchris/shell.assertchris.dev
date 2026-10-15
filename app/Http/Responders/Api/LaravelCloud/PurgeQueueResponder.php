<?php

namespace App\Http\Responders\Api\LaravelCloud;

use AC\LaravelTaggedCache\TaggedCache;
use App\Exceptions\LaravelCloud\LaravelCloudAuthException;
use App\Exceptions\LaravelCloud\LaravelCloudClientException;
use App\Exceptions\LaravelCloud\LaravelCloudRateLimitException;
use App\Models\Account;
use App\Services\LaravelCloudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurgeQueueResponder
{
    public function __invoke(Request $request, Account $account, string $instance): JsonResponse
    {
        try {
            $data = (new LaravelCloudService($account))->purgeQueue($instance);
        } catch (LaravelCloudRateLimitException $e) {
            return response()->json(['error' => $e->getMessage()], 429, ['Retry-After' => $e->retryAfter]);
        } catch (LaravelCloudAuthException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        } catch (LaravelCloudClientException $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        TaggedCache::for(tags: ["laravel-cloud:{$account->id}"])->flush();

        return response()->json($data);
    }
}
