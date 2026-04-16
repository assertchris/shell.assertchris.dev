<?php

namespace App\Mcp\Tools;

use App\Models\Account;
use App\Services\SlackService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List all Slack channels visible to the connected account.')]
class ListSlackChannelsTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $request->validate([
            'account_id' => ['required', 'integer'],
        ]);

        $account = Account::query()->find($request->get('account_id'));

        if (! $account || $account->user_id !== $request->user()?->id) {
            return Response::error('Account not found.');
        }

        if (! $account->isSlackConnected()) {
            return Response::error('Slack is not connected for this account.');
        }

        try {
            $channels = app(SlackService::class)->listChannels($account, $request->get('cursor'));

            return Response::json($channels);
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
            'cursor' => $schema->string()->description('Pagination cursor from a previous response')->nullable(),
        ];
    }
}
