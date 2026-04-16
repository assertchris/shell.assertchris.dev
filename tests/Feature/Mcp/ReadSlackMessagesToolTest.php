<?php

use App\Mcp\Servers\ShellServer;
use App\Mcp\Tools\ReadSlackMessagesTool;
use App\Models\Account;
use App\Models\User;
use App\Services\SlackService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('unauthenticated request returns error', function () {
    $account = Account::factory()->withSlack()->create();

    ShellServer::tool(ReadSlackMessagesTool::class, ['account_id' => $account->id, 'channel_id' => 'C001'])
        ->assertHasErrors(['Account not found.']);
});

test('account not owned by authenticated user returns error', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $account = Account::factory()->withSlack()->create(['user_id' => $otherUser->id]);

    ShellServer::actingAs($user)
        ->tool(ReadSlackMessagesTool::class, ['account_id' => $account->id, 'channel_id' => 'C001'])
        ->assertHasErrors(['Account not found.']);
});

test('account not slack connected returns error', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create([
        'user_id' => $user->id,
        'slack_used' => false,
        'slack_access_token' => null,
    ]);

    ShellServer::actingAs($user)
        ->tool(ReadSlackMessagesTool::class, ['account_id' => $account->id, 'channel_id' => 'C001'])
        ->assertHasErrors(['Slack is not connected for this account.']);
});

test('happy path returns messages and next cursor', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withSlack()->create(['user_id' => $user->id]);

    $result = [
        'messages' => [
            ['ts' => '1234567890.000100', 'user' => 'U001', 'text' => 'Hello world'],
            ['ts' => '1234567891.000200', 'user' => 'U002', 'text' => 'Hi there'],
        ],
        'next_cursor' => 'dXNlcjpVMDYxNTU3MjA=',
    ];

    $this->mock(SlackService::class, function ($mock) use ($account, $result) {
        $mock->shouldReceive('listMessages')
            ->once()
            ->withArgs(fn ($arg) => $arg->id === $account->id)
            ->andReturn($result);
    });

    ShellServer::actingAs($user)
        ->tool(ReadSlackMessagesTool::class, ['account_id' => $account->id, 'channel_id' => 'C001'])
        ->assertOk()
        ->assertSee('Hello world')
        ->assertSee('next_cursor');
});

test('service error returns mcp error response', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withSlack()->create(['user_id' => $user->id]);

    $this->mock(SlackService::class, function ($mock) {
        $mock->shouldReceive('listMessages')
            ->once()
            ->andThrow(new \RuntimeException('missing_scope'));
    });

    ShellServer::actingAs($user)
        ->tool(ReadSlackMessagesTool::class, ['account_id' => $account->id, 'channel_id' => 'C001'])
        ->assertHasErrors(['Slack API unavailable: missing_scope']);
});
