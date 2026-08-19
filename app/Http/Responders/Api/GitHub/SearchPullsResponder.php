<?php

namespace App\Http\Responders\Api\GitHub;

use App\Exceptions\GitHub\GitHubRateLimitException;
use App\Http\Resources\GitHub\GitHubPullResource;
use App\Http\Resources\GitHub\GitHubSearchResource;
use App\Models\Account;
use App\Services\GitHubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SearchPullsResponder
{
    public function __invoke(Request $request, Account $account): AnonymousResourceCollection|JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string'],
            'page' => ['integer', 'min:1'],
            'per_page' => ['integer', 'min:1', 'max:100'],
        ]);

        try {
            $params = array_filter([
                'page' => $validated['page'] ?? null,
                'per_page' => $validated['per_page'] ?? null,
            ]);

            $data = (new GitHubService($account))->searchPulls($validated['q'], $params);

            return (new GitHubSearchResource($data, GitHubPullResource::collection($data['items'] ?? [])))->respond();
        } catch (GitHubRateLimitException $e) {
            return response()->json(['error' => 'GitHub rate limit exceeded.'], 429, [
                'Retry-After' => $e->retryAfter,
            ]);
        }
    }
}
