<?php

namespace App\Http\Responders\Api\GitHub;

use App\Exceptions\GitHub\GitHubRateLimitException;
use App\Http\Resources\GitHub\GitHubIssueResource;
use App\Models\Account;
use App\Services\GitHubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListRepoIssuesResponder
{
    public function __invoke(Request $request, Account $account, string $owner, string $repo): AnonymousResourceCollection|JsonResponse
    {
        $validated = $request->validate([
            'state' => ['string', 'in:open,closed,all'],
            'labels' => ['string'],
            'page' => ['integer', 'min:1'],
            'per_page' => ['integer', 'min:1', 'max:100'],
        ]);

        $validated['state'] ??= 'open';
        $validated['page'] ??= 1;
        $validated['per_page'] ??= 30;

        try {
            $data = (new GitHubService($account))->repoIssues($owner, $repo, $validated);

            return GitHubIssueResource::collection($data)
                ->additional(['meta' => ['total_count' => count($data)]]);
        } catch (GitHubRateLimitException $e) {
            return response()->json(['error' => 'GitHub rate limit exceeded.'], 429, [
                'Retry-After' => $e->retryAfter,
            ]);
        }
    }
}
