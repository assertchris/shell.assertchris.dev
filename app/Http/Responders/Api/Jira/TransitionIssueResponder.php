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

class TransitionIssueResponder
{
    public function __invoke(Request $request, Account $account, string $key): JsonResponse
    {
        $validated = $request->validate([
            'transition_id' => ['required', 'string'],
        ]);

        try {
            (new JiraService($account))->transitionIssue($key, $validated['transition_id']);
        } catch (JiraRateLimitException $e) {
            return response()->json(['error' => 'Jira rate limit exceeded.'], 429, ['Retry-After' => $e->retryAfter]);
        } catch (JiraAuthException) {
            return response()->json(['error' => 'Jira authentication failed.'], 401);
        } catch (JiraForbiddenException) {
            return response()->json(['error' => 'Jira access forbidden.'], 403);
        }

        Cache::forget(CacheKey::for('jira', $account->id, 'issue', $key));
        Cache::forget(CacheKey::for('jira', $account->id, 'issue', $key, 'transitions'));

        return response()->json([], 204);
    }
}
