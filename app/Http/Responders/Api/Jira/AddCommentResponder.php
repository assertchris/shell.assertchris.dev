<?php

namespace App\Http\Responders\Api\Jira;

use App\Exceptions\Jira\JiraAuthException;
use App\Exceptions\Jira\JiraForbiddenException;
use App\Exceptions\Jira\JiraRateLimitException;
use App\Http\Resources\Jira\CommentResource;
use App\Models\Account;
use App\Services\JiraService;
use App\Support\CacheKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AddCommentResponder
{
    public function __invoke(Request $request, Account $account, string $key): CommentResource|JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'array'],
            'body.type' => ['required', 'string'],
            'body.version' => ['required', 'integer'],
            'body.content' => ['required', 'array'],
        ]);

        try {
            $data = (new JiraService($account))->addComment($key, $validated['body']);
        } catch (JiraRateLimitException $e) {
            return response()->json(['error' => 'Jira rate limit exceeded.'], 429, ['Retry-After' => $e->retryAfter]);
        } catch (JiraAuthException) {
            return response()->json(['error' => 'Jira authentication failed.'], 401);
        } catch (JiraForbiddenException) {
            return response()->json(['error' => 'Jira access forbidden.'], 403);
        }

        Cache::forget(CacheKey::for('jira', $account->id, 'issue', $key, 'comments'));

        return (new CommentResource($data))->response()->setStatusCode(201);
    }
}
