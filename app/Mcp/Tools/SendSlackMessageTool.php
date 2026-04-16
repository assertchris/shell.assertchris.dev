<?php

namespace App\Mcp\Tools;

use App\Models\Account;
use App\Services\SlackService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Send a message to a Slack channel on behalf of the connected account.')]
class SendSlackMessageTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $request->validate([
            'account_id' => ['required', 'integer'],
            'channel_id' => ['required', 'string'],
            'text' => ['required', 'string'],
        ]);

        $account = Account::query()->find($request->get('account_id'));

        if (! $account || $account->user_id !== $request->user()?->id) {
            return Response::error('Account not found.');
        }

        if (! $account->isSlackConnected()) {
            return Response::error('Slack is not connected for this account.');
        }

        try {
            $result = app(SlackService::class)->sendMessage(
                $account,
                $request->get('channel_id'),
                $request->get('text'),
            );

            return Response::text("Message sent (ts: {$result['ts']})");
        } catch (\Throwable $e) {
            return Response::error('Slack API unavailable: '.$e->getMessage());
        }
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'account_id' => $schema->integer()->description('The Account ID to use for the Slack connection')->required(),
            'channel_id' => $schema->string()->description('The Slack channel ID to send the message to')->required(),
            'text' => $schema->string()->description('The message text to send')->required(),
        ];
    }
}
