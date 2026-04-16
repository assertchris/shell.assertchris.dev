<?php

namespace App\Http\Responders\Api;

use App\Models\Account;
use App\Services\SlackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ListSlackUsersResponder
{
    public function __construct(public SlackService $slackService) {}

    public function __invoke(Request $request, Account $account): JsonResponse
    {
        abort_unless($account->user_id === $request->user()->id, 404);
        abort_unless($account->isSlackConnected(), 404);

        $cursor = $request->query('cursor');

        try {
            $result = $this->slackService->listUsers($account, $cursor ?: null);

            return response()->json(['data' => $result['users'], 'meta' => ['next_cursor' => $result['next_cursor']]]);
        } catch (\Throwable $e) {
            if ($e instanceof HttpException) {
                throw $e;
            }

            return response()->json(['error' => 'Slack API unavailable: '.$e->getMessage()], 503);
        }
    }
}
