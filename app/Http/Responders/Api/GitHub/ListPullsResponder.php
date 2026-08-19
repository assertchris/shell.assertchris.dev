<?php

namespace App\Http\Responders\Api\GitHub;

use App\Exceptions\GitHub\GitHubRateLimitException;
use App\Http\Resources\GitHub\GitHubPullResource;
use App\Models\Account;
use App\Services\GitHubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;

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

            if ($state === 'open') {
                $cacheKey = "github.pulls.{$account->id}.{$owner}.{$repo}.{$state}.{$page}";
                $data = Cache::remember($cacheKey, 60, fn () => (new GitHubService($account))->pulls($owner, $repo, $params));
            } else {
                $data = (new GitHubService($account))->pulls($owner, $repo, $params);
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
