<?php

namespace App\Http\Responders\Api;

use App\Models\Account;
use App\Services\SlackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SendSlackMessageResponder
{
    public function __construct(public SlackService $slackService) {}

    public function __invoke(Request $request, Account $account, string $channelId): JsonResponse
    {
        abort_unless($account->user_id === $request->user()->id, 404);
        abort_unless($account->isSlackConnected(), 404);

        $request->validate([
            'text' => ['required', 'string'],
        ]);

        try {
            $result = $this->slackService->sendMessage($account, $channelId, $request->input('text'));

            return response()->json(['ts' => $result['ts']]);
        } catch (\Throwable $e) {
            if ($e instanceof HttpException) {
                throw $e;
            }

            return response()->json(['error' => 'Slack API unavailable: '.$e->getMessage()], 503);
        }
    }
}
