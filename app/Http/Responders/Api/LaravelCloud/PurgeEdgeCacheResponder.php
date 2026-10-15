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

class PurgeEdgeCacheResponder
{
    public function __invoke(Request $request, Account $account, string $environment): JsonResponse
    {
        $validated = $request->validate([
            'path' => ['sometimes', 'string'],
            'prefix' => ['sometimes', 'string'],
            'tag' => ['sometimes', 'string'],
        ]);

        $provided = array_filter(array_intersect_key($validated, array_flip(['path', 'prefix', 'tag'])));

        if (count($provided) !== 1) {
            return response()->json(['error' => 'Exactly one of path, prefix, or tag must be provided.'], 422);
        }

        try {
            $data = (new LaravelCloudService($account))->purgeEdgeCache($environment, $validated);
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
