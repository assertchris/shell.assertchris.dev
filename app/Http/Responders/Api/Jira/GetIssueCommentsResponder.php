<?php

namespace App\Http\Responders\Api\Jira;

use App\Exceptions\Jira\JiraAuthException;
use App\Exceptions\Jira\JiraForbiddenException;
use App\Exceptions\Jira\JiraRateLimitException;
use App\Http\Resources\Jira\CommentResource;
use App\Models\Account;
use App\Services\JiraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GetIssueCommentsResponder
{
    public function __invoke(Request $request, Account $account, string $key): AnonymousResourceCollection|JsonResponse
    {
        try {
            $data = (new JiraService($account))->issueComments($key);
        } catch (JiraRateLimitException $e) {
            return response()->json(['error' => 'Jira rate limit exceeded.'], 429, ['Retry-After' => $e->retryAfter]);
        } catch (JiraAuthException) {
            return response()->json(['error' => 'Jira authentication failed.'], 401);
        } catch (JiraForbiddenException) {
            return response()->json(['error' => 'Jira access forbidden.'], 403);
        }

        $items = $data['comments'] ?? $data;

        return CommentResource::collection($items)
            ->additional(['meta' => ['total_count' => count($items)]]);
    }
}
