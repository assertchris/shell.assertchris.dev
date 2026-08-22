<?php

namespace App\Http\Responders\Api\Jira;

use App\Exceptions\Jira\JiraAuthException;
use App\Exceptions\Jira\JiraForbiddenException;
use App\Exceptions\Jira\JiraRateLimitException;
use App\Http\Resources\Jira\IssueResource;
use App\Models\Account;
use App\Services\JiraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListMyMentionsResponder
{
    public function __invoke(Request $request, Account $account): AnonymousResourceCollection|JsonResponse
    {
        $validated = $request->validate([
            'nextPageToken' => ['nullable', 'string'],
            'maxResults' => ['integer', 'min:1', 'max:100'],
        ]);

        try {
            $data = (new JiraService($account))->issuesMentioningMe($validated);
        } catch (JiraRateLimitException $e) {
            return response()->json(['error' => 'Jira rate limit exceeded.'], 429, ['Retry-After' => $e->retryAfter]);
        } catch (JiraAuthException) {
            return response()->json(['error' => 'Jira authentication failed.'], 401);
        } catch (JiraForbiddenException) {
            return response()->json(['error' => 'Jira access forbidden.'], 403);
        }

        return IssueResource::collection($data['issues'] ?? [])
            ->additional(['meta' => [
                'next_page_token' => $data['nextPageToken'] ?? null,
                'is_last' => $data['isLast'] ?? true,
            ]]);
    }
}
