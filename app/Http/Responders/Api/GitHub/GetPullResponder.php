<?php

namespace App\Http\Responders\Api\GitHub;

use App\Exceptions\GitHub\GitHubRateLimitException;
use App\Http\Resources\GitHub\GitHubPullResource;
use App\Models\Account;
use App\Services\GitHubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetPullResponder
{
    public function __invoke(Request $request, Account $account, string $owner, string $repo, string $number): GitHubPullResource|JsonResponse
    {
        $pullNumber = (int) $number;

        try {
            $data = (new GitHubService($account))->pull($owner, $repo, $pullNumber);

            if (empty($data)) {
                abort(404);
            }

            return new GitHubPullResource($data);
        } catch (GitHubRateLimitException $e) {
            return response()->json(['error' => 'GitHub rate limit exceeded.'], 429, [
                'Retry-After' => $e->retryAfter,
            ]);
        }
    }
}
