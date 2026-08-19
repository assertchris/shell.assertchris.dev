<?php

namespace App\Http\Responders\Api\GitHub;

use App\Exceptions\GitHub\GitHubRateLimitException;
use App\Http\Resources\GitHub\GitHubRepoResource;
use App\Models\Account;
use App\Services\GitHubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetRepoResponder
{
    public function __invoke(Request $request, Account $account, string $owner, string $repo): GitHubRepoResource|JsonResponse
    {
        try {
            $data = (new GitHubService($account))->repo($owner, $repo);

            if (empty($data)) {
                abort(404);
            }

            return new GitHubRepoResource($data);
        } catch (GitHubRateLimitException $e) {
            return response()->json(['error' => 'GitHub rate limit exceeded.'], 429, [
                'Retry-After' => $e->retryAfter,
            ]);
        }
    }
}
