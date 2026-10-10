<?php

namespace App\Http\Responders\Api\GitHub;

use AC\LaravelTaggedCache\TaggedCache;
use App\Exceptions\GitHub\GitHubRateLimitException;
use App\Http\Resources\GitHub\GitHubPullResource;
use App\Models\Account;
use App\Services\GitHubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListPullsResponder
{
    public function __invoke(Request $request, Account $account, string $owner, string $repo): AnonymousResourceCollection|JsonResponse
    {
        $validated = $request->validate([
            'state' => ['string', 'in:open,closed,all'],
            'page' => ['integer', 'min:1'],
            'per_page' => ['integer', 'min:1', 'max:100'],
        ]);

        $state = $validated['state'] ?? 'open';
        $page = $validated['page'] ?? 1;
        $perPage = $validated['per_page'] ?? 30;

        try {
            $params = ['state' => $state, 'page' => $page, 'per_page' => $perPage];

            $data = (new GitHubService($account))->pulls($owner, $repo, $params);

            if ($state === 'open') {
                TaggedCache::for(
                    tags: ['github', "github:{$account->id}"],
                    context: array_merge(['type' => 'pulls', 'owner' => $owner, 'repo' => $repo], $params),
                )->put($data, 60);
            }

            return GitHubPullResource::collection($data)
                ->additional(['meta' => ['total_count' => count($data)]]);
        } catch (GitHubRateLimitException $e) {
            return response()->json(['error' => 'GitHub rate limit exceeded.'], 429, [
                'Retry-After' => $e->retryAfter,
            ]);
        }
    }
}
