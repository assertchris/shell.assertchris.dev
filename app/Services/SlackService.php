<?php

namespace App\Services;

use App\Models\Account;
use Illuminate\Support\Facades\Http;

class SlackService
{
    /**
     * @return array<int, array{id: string, name: string, is_private: bool, num_members: int}>
     */
    public function listChannels(Account $account, ?string $cursor = null): array
    {
        $params = [
            'limit' => 200,
            'exclude_archived' => true,
            'types' => 'public_channel,private_channel',
        ];

        if ($cursor !== null) {
            $params['cursor'] = $cursor;
        }

        $response = Http::withToken($account->slack_access_token)
            ->get('https://slack.com/api/conversations.list', $params);

        if (! $response->json('ok')) {
            throw new \RuntimeException($response->json('error'));
        }

        return array_map(
            fn (array $channel) => [
                'id' => $channel['id'],
                'name' => $channel['name'],
                'is_private' => $channel['is_private'],
                'num_members' => $channel['num_members'] ?? 0,
            ],
            $response->json('channels') ?? []
        );
    }

    /**
     * @return array{messages: array<int, array{ts: string, user: string, text: string}>, next_cursor: string|null}
     */
    public function listMessages(Account $account, string $channelId, int $limit = 50, ?string $cursor = null): array
    {
        $params = [
            'channel' => $channelId,
            'limit' => $limit,
        ];

        if ($cursor !== null) {
            $params['cursor'] = $cursor;
        }

        $response = Http::withToken($account->slack_access_token)
            ->get('https://slack.com/api/conversations.history', $params);

        if (! $response->json('ok')) {
            throw new \RuntimeException($response->json('error'));
        }

        $messages = array_map(
            fn (array $message) => [
                'ts' => $message['ts'],
                'user' => $message['user'] ?? '',
                'text' => $message['text'] ?? '',
            ],
            $response->json('messages') ?? []
        );

        return [
            'messages' => $messages,
            'next_cursor' => $response->json('response_metadata.next_cursor') ?: null,
        ];
    }

    /**
     * @return array{ok: bool, ts: string}
     */
    public function sendMessage(Account $account, string $channelId, string $text): array
    {
        $response = Http::withToken($account->slack_access_token)
            ->post('https://slack.com/api/chat.postMessage', [
                'channel' => $channelId,
                'text' => $text,
            ]);

        if (! $response->json('ok')) {
            throw new \RuntimeException($response->json('error'));
        }

        return [
            'ok' => true,
            'ts' => $response->json('ts'),
        ];
    }
}
