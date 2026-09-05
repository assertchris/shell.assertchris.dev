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

class CreateIssueResponder
{
    public function __invoke(Request $request, Account $account): IssueResource|JsonResponse
    {
        $validated = $request->validate([
            'fields' => ['required', 'array'],
            'fields.project.key' => ['required', 'string'],
            'fields.issuetype.name' => ['required', 'string'],
            'fields.summary' => ['required', 'string'],
            'fields.description' => ['sometimes', 'nullable', 'string'],
        ]);

        try {
            $data = (new JiraService($account))->createIssue($validated['fields']);
        } catch (JiraRateLimitException $e) {
            return response()->json(['error' => 'Jira rate limit exceeded.'], 429, ['Retry-After' => $e->retryAfter]);
        } catch (JiraAuthException) {
            return response()->json(['error' => 'Jira authentication failed.'], 401);
        } catch (JiraForbiddenException) {
            return response()->json(['error' => 'Jira access forbidden.'], 403);
        }

        return (new IssueResource($data))->response()->setStatusCode(201);
    }
}
