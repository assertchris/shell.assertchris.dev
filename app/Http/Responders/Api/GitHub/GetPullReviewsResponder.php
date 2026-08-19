<?php

namespace App\Http\Responders\Api\GitHub;

use App\Exceptions\GitHub\GitHubRateLimitException;
use App\Http\Resources\GitHub\GitHubReviewResource;
use App\Models\Account;
use App\Services\GitHubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GetPullReviewsResponder
{
    public function __invoke(Request $request, Account $account, string $owner, string $repo, string $number): AnonymousResourceCollection|JsonResponse
    {
        try {
            $data = (new GitHubService($account))->pullReviews($owner, $repo, (int) $number);

            return GitHubReviewResource::collection($data)
                ->additional(['meta' => ['total_count' => count($data)]]);
        } catch (GitHubRateLimitException $e) {
            return response()->json(['error' => 'GitHub rate limit exceeded.'], 429, [
                'Retry-After' => $e->retryAfter,
            ]);
        }
    }
}
