<?php

namespace App\Http\Responders\Api\GitHub;

use App\Exceptions\GitHub\GitHubRateLimitException;
use App\Models\Account;
use App\Services\GitHubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetPullRequestedReviewersResponder
{
    public function __invoke(Request $request, Account $account, string $owner, string $repo, string $number): JsonResponse
    {
        try {
            $data = (new GitHubService($account))->pullRequestedReviewers($owner, $repo, (int) $number);

            return response()->json($data);
        } catch (GitHubRateLimitException $e) {
            return response()->json(['error' => 'GitHub rate limit exceeded.'], 429, [
                'Retry-After' => $e->retryAfter,
            ]);
        }
    }
}
