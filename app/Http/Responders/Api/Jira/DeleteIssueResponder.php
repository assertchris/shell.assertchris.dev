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

class DeleteIssueResponder
{
    public function __invoke(Request $request, Account $account, string $key): JsonResponse
    {
        try {
            (new JiraService($account))->deleteIssue($key);
        } catch (JiraRateLimitException $e) {
            return response()->json(['error' => 'Jira rate limit exceeded.'], 429, ['Retry-After' => $e->retryAfter]);
        } catch (JiraAuthException) {
            return response()->json(['error' => 'Jira authentication failed.'], 401);
        } catch (JiraForbiddenException) {
            return response()->json(['error' => 'Jira access forbidden.'], 403);
        }

        CacheKey::for('jira', $account->id, 'issue', $key)->forget();
        CacheKey::hashed([], 'jira', $account->id, 'user', 'assigned')->forget();
        CacheKey::hashed([], 'jira', $account->id, 'user', 'reported')->forget();
        CacheKey::hashed([], 'jira', $account->id, 'user', 'activity')->forget();

        return response()->json([], 204);
    }
}
