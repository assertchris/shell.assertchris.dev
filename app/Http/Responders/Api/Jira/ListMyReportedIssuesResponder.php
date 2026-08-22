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

class ListMyReportedIssuesResponder
{
    public function __invoke(Request $request, Account $account): AnonymousResourceCollection|JsonResponse
    {
        $validated = $request->validate([
            'startAt' => ['integer', 'min:0'],
            'maxResults' => ['integer', 'min:1', 'max:100'],
        ]);

        try {
            $data = (new JiraService($account))->myReportedIssues($validated);
        } catch (JiraRateLimitException $e) {
            return response()->json(['error' => 'Jira rate limit exceeded.'], 429, ['Retry-After' => $e->retryAfter]);
        } catch (JiraAuthException) {
            return response()->json(['error' => 'Jira authentication failed.'], 401);
        } catch (JiraForbiddenException) {
            return response()->json(['error' => 'Jira access forbidden.'], 403);
        }

        return IssueResource::collection($data['issues'] ?? [])
            ->additional(['meta' => [
                'start_at' => $data['startAt'] ?? 0,
                'max_results' => $data['maxResults'] ?? 0,
                'total' => $data['total'] ?? 0,
            ]]);
    }
}
