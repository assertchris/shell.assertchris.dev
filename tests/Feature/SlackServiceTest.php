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
