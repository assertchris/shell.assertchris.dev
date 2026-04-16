<?php

namespace App\Http\Responders\Api;

use App\Models\Account;
use App\Services\SlackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ListSlackMessagesResponder
{
    public function __construct(public SlackService $slackService) {}

    public function __invoke(Request $request, Account $account, string $channelId): JsonResponse
    {
        abort_unless($account->user_id === $request->user()->id, 404);
        abort_unless($account->isSlackConnected(), 404);

        $limit = min(200, max(1, (int) $request->query('limit', 50)));
        $cursor = $request->query('cursor');

        try {
            $result = $this->slackService->listMessages($account, $channelId, $limit, $cursor ?: null);

            return response()->json([
                'data' => $result['messages'],
                'meta' => [
                    'next_cursor' => $result['next_cursor'],
                ],
            ]);
        } catch (\Throwable $e) {
            if ($e instanceof HttpException) {
                throw $e;
            }

            return response()->json(['error' => 'Slack API unavailable: '.$e->getMessage()], 503);
        }
    }
}
