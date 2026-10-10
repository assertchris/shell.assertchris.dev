<?php

namespace App\Http\Responders\Api\Jira;

use App\Exceptions\Jira\JiraAuthException;
use App\Exceptions\Jira\JiraForbiddenException;
use App\Exceptions\Jira\JiraRateLimitException;
use App\Models\Account;
use App\Services\JiraService;
use App\Support\CacheKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MoveIssuesToSprintResponder
{
    public function __invoke(Request $request, Account $account, int $sprintId): JsonResponse
    {
        $validated = $request->validate([
            'issue_keys' => ['required', 'array'],
            'issue_keys.*' => ['required', 'string'],
        ]);

        try {
            (new JiraService($account))->moveIssuesToSprint($sprintId, $validated['issue_keys']);
        } catch (JiraRateLimitException $e) {
            return response()->json(['error' => 'Jira rate limit exceeded.'], 429, ['Retry-After' => $e->retryAfter]);
        } catch (JiraAuthException) {
            return response()->json(['error' => 'Jira authentication failed.'], 401);
        } catch (JiraForbiddenException) {
            return response()->json(['error' => 'Jira access forbidden.'], 403);
        }

        Cache::forget(CacheKey::hashed([], 'jira', $account->id, 'sprint', $sprintId, 'issues'));

        return response()->json([], 204);
    }
}
