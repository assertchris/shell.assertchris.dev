<?php

namespace App\Http\Responders\Api\GitHub;

use App\Exceptions\GitHub\GitHubRateLimitException;
use App\Models\Account;
use App\Services\GitHubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetPullReviewCommentsResponder
{
    public function __invoke(Request $request, Account $account, string $owner, string $repo, string $number, string $review_id): JsonResponse
    {
        try {
            $data = (new GitHubService($account))->pullReviewComments($owner, $repo, (int) $number, (int) $review_id);

            return response()->json(['data' => $data, 'meta' => ['total_count' => count($data)]]);
        } catch (GitHubRateLimitException $e) {
            return response()->json(['error' => 'GitHub rate limit exceeded.'], 429, [
                'Retry-After' => $e->retryAfter,
            ]);
        }
    }
}
