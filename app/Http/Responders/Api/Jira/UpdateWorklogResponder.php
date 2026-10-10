<?php

namespace App\Http\Responders\Api\Jira;

use App\Exceptions\Jira\JiraAuthException;
use App\Exceptions\Jira\JiraForbiddenException;
use App\Exceptions\Jira\JiraRateLimitException;
use App\Http\Resources\Jira\WorklogResource;
use App\Models\Account;
use App\Services\JiraService;
use App\Support\CacheKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class UpdateWorklogResponder
{
    public function __invoke(Request $request, Account $account, string $key, string $worklogId): WorklogResource|JsonResponse
    {
        $validated = $request->validate([
            'time_spent' => ['required', 'string'],
            'started' => ['required', 'string'],
        ]);

        try {
            $data = (new JiraService($account))->updateWorklog($key, $worklogId, [
                'timeSpent' => $validated['time_spent'],
                'started' => $validated['started'],
            ]);
        } catch (JiraRateLimitException $e) {
            return response()->json(['error' => 'Jira rate limit exceeded.'], 429, ['Retry-After' => $e->retryAfter]);
        } catch (JiraAuthException) {
            return response()->json(['error' => 'Jira authentication failed.'], 401);
        } catch (JiraForbiddenException) {
            return response()->json(['error' => 'Jira access forbidden.'], 403);
        }

        Cache::forget(CacheKey::for('jira', $account->id, 'issue', $key, 'worklogs'));

        return new WorklogResource($data);
    }
}
