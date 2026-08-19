<?php

namespace App\Http\Responders\Api\GitHub;

use App\Exceptions\GitHub\GitHubRateLimitException;
use App\Http\Resources\GitHub\GitHubNotificationResource;
use App\Models\Account;
use App\Services\GitHubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetNotificationResponder
{
    public function __invoke(Request $request, Account $account, string $thread_id): GitHubNotificationResource|JsonResponse
    {
        try {
            $data = (new GitHubService($account))->notification($thread_id);

            if (empty($data)) {
                abort(404);
            }

            return new GitHubNotificationResource($data);
        } catch (GitHubRateLimitException $e) {
            return response()->json(['error' => 'GitHub rate limit exceeded.'], 429, [
                'Retry-After' => $e->retryAfter,
            ]);
        }
    }
}
