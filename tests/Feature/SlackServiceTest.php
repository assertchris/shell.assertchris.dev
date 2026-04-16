<?php

use App\Models\Account;
use App\Models\User;
use App\Services\SlackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('listChannels returns shaped array from mocked response', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withSlack()->create(['user_id' => $user->id]);

    Http::fake([
        'slack.com/api/conversations.list*' => Http::response([
            'ok' => true,
            'channels' => [
                [
                    'id' => 'C001',
                    'name' => 'general',
                    'is_private' => false,
                    'num_members' => 10,
                ],
                [
                    'id' => 'C002',
                    'name' => 'secret',
                    'is_private' => true,
                    'num_members' => 3,
                ],
            ],
        ]),
    ]);

    $channels = app(SlackService::class)->listChannels($account);

    expect($channels)->toHaveCount(2)
        ->and($channels[0])->toBe([
            'id' => 'C001',
            'name' => 'general',
            'is_private' => false,
            'num_members' => 10,
        ])
        ->and($channels[1])->toBe([
            'id' => 'C002',
            'name' => 'secret',
            'is_private' => true,
            'num_members' => 3,
        ]);
});

test('listChannels passes cursor when provided', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withSlack()->create(['user_id' => $user->id]);

    Http::fake([
        'slack.com/api/conversations.list*' => Http::response([
            'ok' => true,
            'channels' => [],
        ]),
    ]);

    app(SlackService::class)->listChannels($account, 'next-cursor-value');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'conversations.list')
            && $request['cursor'] === 'next-cursor-value';
    });
});

test('listChannels normalises DM name to dm:<user_id>', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withSlack()->create(['user_id' => $user->id]);

    Http::fake([
        'slack.com/api/conversations.list*' => Http::response([
            'ok' => true,
            'channels' => [
                [
                    'id' => 'D001',
                    'user' => 'U999',
                    'is_private' => true,
                    'num_members' => 2,
                ],
            ],
        ]),
    ]);

    $channels = app(SlackService::class)->listChannels($account);

    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBe([
            'id' => 'D001',
            'name' => 'dm:U999',
            'is_private' => true,
            'num_members' => 2,
        ]);
});

test('listChannels passes through mpim name as-is', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withSlack()->create(['user_id' => $user->id]);

    Http::fake([
        'slack.com/api/conversations.list*' => Http::response([
            'ok' => true,
            'channels' => [
                [
                    'id' => 'G001',
                    'name' => 'mpdm-alice--bob--charlie-1',
                    'is_private' => true,
                    'num_members' => 3,
                ],
            ],
        ]),
    ]);

    $channels = app(SlackService::class)->listChannels($account);

    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBe([
            'id' => 'G001',
            'name' => 'mpdm-alice--bob--charlie-1',
            'is_private' => true,
            'num_members' => 3,
        ]);
});

test('listChannels throws on non-ok response', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withSlack()->create(['user_id' => $user->id]);

    Http::fake([
        'slack.com/api/conversations.list*' => Http::response([
            'ok' => false,
            'error' => 'not_authed',
        ]),
    ]);

    expect(fn () => app(SlackService::class)->listChannels($account))
        ->toThrow(\RuntimeException::class, 'not_authed');
});

test('listMessages returns messages and next cursor', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withSlack()->create(['user_id' => $user->id]);

    Http::fake([
        'slack.com/api/conversations.history*' => Http::response([
            'ok' => true,
            'messages' => [
                ['ts' => '1234567890.000100', 'user' => 'U001', 'text' => 'Hello world'],
                ['ts' => '1234567890.000200', 'user' => 'U002', 'text' => 'Hi there'],
            ],
            'response_metadata' => [
                'next_cursor' => 'cursor-abc',
            ],
        ]),
    ]);

    $result = app(SlackService::class)->listMessages($account, 'C001');

    expect($result['messages'])->toHaveCount(2)
        ->and($result['messages'][0])->toBe([
            'ts' => '1234567890.000100',
            'user' => 'U001',
            'text' => 'Hello world',
        ])
        ->and($result['next_cursor'])->toBe('cursor-abc');
});

test('listMessages returns null next cursor when not present', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withSlack()->create(['user_id' => $user->id]);

    Http::fake([
        'slack.com/api/conversations.history*' => Http::response([
            'ok' => true,
            'messages' => [],
            'response_metadata' => ['next_cursor' => ''],
        ]),
    ]);

    $result = app(SlackService::class)->listMessages($account, 'C001');

    expect($result['next_cursor'])->toBeNull();
});

test('listMessages throws on non-ok response', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withSlack()->create(['user_id' => $user->id]);

    Http::fake([
        'slack.com/api/conversations.history*' => Http::response([
            'ok' => false,
            'error' => 'channel_not_found',
        ]),
    ]);

    expect(fn () => app(SlackService::class)->listMessages($account, 'C_BAD'))
        ->toThrow(\RuntimeException::class, 'channel_not_found');
});

test('sendMessage posts correctly and returns ts', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withSlack()->create(['user_id' => $user->id]);

    Http::fake([
        'slack.com/api/chat.postMessage' => Http::response([
            'ok' => true,
            'ts' => '1234567890.000300',
        ]),
    ]);

    $result = app(SlackService::class)->sendMessage($account, 'C001', 'Hello from agent!');

    expect($result)->toBe([
        'ok' => true,
        'ts' => '1234567890.000300',
    ]);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'chat.postMessage')
            && $request['channel'] === 'C001'
            && $request['text'] === 'Hello from agent!';
    });
});

test('sendMessage throws on non-ok response', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withSlack()->create(['user_id' => $user->id]);

    Http::fake([
        'slack.com/api/chat.postMessage' => Http::response([
            'ok' => false,
            'error' => 'channel_not_found',
        ]),
    ]);

    expect(fn () => app(SlackService::class)->sendMessage($account, 'C_BAD', 'Hi'))
        ->toThrow(\RuntimeException::class, 'channel_not_found');
});

test('listUsers returns shaped array and filters deleted users', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withSlack()->create(['user_id' => $user->id]);

    Http::fake([
        'slack.com/api/users.list*' => Http::response([
            'ok' => true,
            'members' => [
                [
                    'id' => 'U001',
                    'name' => 'alice',
                    'real_name' => 'Alice Smith',
                    'is_bot' => false,
                    'deleted' => false,
                ],
                [
                    'id' => 'U002',
                    'name' => 'bob',
                    'real_name' => 'Bob Jones',
                    'is_bot' => false,
                    'deleted' => true,
                ],
                [
                    'id' => 'U003',
                    'name' => 'slackbot',
                    'real_name' => 'Slackbot',
                    'is_bot' => true,
                    'deleted' => false,
                ],
            ],
            'response_metadata' => ['next_cursor' => ''],
        ]),
    ]);

    $result = app(SlackService::class)->listUsers($account);

    expect($result['users'])->toHaveCount(2)
        ->and($result['users'][0])->toBe([
            'id' => 'U001',
            'name' => 'alice',
            'real_name' => 'Alice Smith',
            'is_bot' => false,
        ])
        ->and($result['users'][1])->toBe([
            'id' => 'U003',
            'name' => 'slackbot',
            'real_name' => 'Slackbot',
            'is_bot' => true,
        ])
        ->and($result['next_cursor'])->toBeNull();
});

test('listUsers passes cursor when provided', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withSlack()->create(['user_id' => $user->id]);

    Http::fake([
        'slack.com/api/users.list*' => Http::response([
            'ok' => true,
            'members' => [],
            'response_metadata' => ['next_cursor' => 'next-page-cursor'],
        ]),
    ]);

    $result = app(SlackService::class)->listUsers($account, 'some-cursor');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'users.list')
            && $request['cursor'] === 'some-cursor';
    });

    expect($result['next_cursor'])->toBe('next-page-cursor');
});

test('listUsers throws on non-ok response', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withSlack()->create(['user_id' => $user->id]);

    Http::fake([
        'slack.com/api/users.list*' => Http::response([
            'ok' => false,
            'error' => 'invalid_auth',
        ]),
    ]);

    expect(fn () => app(SlackService::class)->listUsers($account))
        ->toThrow(\RuntimeException::class, 'invalid_auth');
});

test('openConversation returns channel_id', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withSlack()->create(['user_id' => $user->id]);

    Http::fake([
        'slack.com/api/conversations.open' => Http::response([
            'ok' => true,
            'channel' => ['id' => 'D001ABCDE'],
        ]),
    ]);

    $result = app(SlackService::class)->openConversation($account, 'U001');

    expect($result)->toBe(['channel_id' => 'D001ABCDE']);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'conversations.open')
            && $request['users'] === 'U001';
    });
});

test('openConversation throws on non-ok response', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withSlack()->create(['user_id' => $user->id]);

    Http::fake([
        'slack.com/api/conversations.open' => Http::response([
            'ok' => false,
            'error' => 'user_not_found',
        ]),
    ]);

    expect(fn () => app(SlackService::class)->openConversation($account, 'U_BAD'))
        ->toThrow(\RuntimeException::class, 'user_not_found');
});
