<?php

namespace App\Http\Responders\Api\Jira;

use AC\LaravelTaggedCache\TaggedCache;
use App\Exceptions\Jira\JiraAuthException;
use App\Exceptions\Jira\JiraForbiddenException;
use App\Exceptions\Jira\JiraRateLimitException;
use App\Models\Account;
use App\Services\JiraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UpdateIssueResponder
{
    public function __invoke(Request $request, Account $account, string $key): JsonResponse
    {
        $validated = $request->validate([
            'fields' => ['required', 'array'],
            'fields.description' => ['sometimes', 'nullable', 'array'],
        ]);

        try {
            (new JiraService($account))->updateIssue($key, $validated['fields']);
        } catch (JiraRateLimitException $e) {
            return response()->json(['error' => 'Jira rate limit exceeded.'], 429, ['Retry-After' => $e->retryAfter]);
        } catch (JiraAuthException) {
            return response()->json(['error' => 'Jira authentication failed.'], 401);
        } catch (JiraForbiddenException) {
            return response()->json(['error' => 'Jira access forbidden.'], 403);
        }

        TaggedCache::for(tags: ["jira:{$account->id}:issue:{$key}"])->flush();
        TaggedCache::for(tags: ["jira:{$account->id}"])->flush();

        return response()->json([], 204);
    }
}
