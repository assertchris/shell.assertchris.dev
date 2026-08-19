<?php

namespace App\Http\Responders\Api\GitHub;

use App\Exceptions\GitHub\GitHubRateLimitException;
use App\Http\Resources\GitHub\GitHubIssueResource;
use App\Models\Account;
use App\Services\GitHubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetIssueResponder
{
    public function __invoke(Request $request, Account $account, string $owner, string $repo, string $number): GitHubIssueResource|JsonResponse
    {
        try {
            $data = (new GitHubService($account))->issue($owner, $repo, (int) $number);

            if (empty($data)) {
                abort(404);
            }

            return new GitHubIssueResource($data);
        } catch (GitHubRateLimitException $e) {
            return response()->json(['error' => 'GitHub rate limit exceeded.'], 429, [
                'Retry-After' => $e->retryAfter,
            ]);
        }
    }
}
