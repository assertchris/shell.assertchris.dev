<?php

use App\Mcp\Servers\ShellServer;
use App\Mcp\Tools\SendSlackMessageTool;
use App\Models\Account;
use App\Models\User;
use App\Services\SlackService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('unauthenticated request returns error', function () {
    $account = Account::factory()->withSlack()->create();

    ShellServer::tool(SendSlackMessageTool::class, ['account_id' => $account->id, 'channel_id' => 'C001', 'text' => 'Hello'])
        ->assertHasErrors(['Account not found.']);
});

test('account not owned by authenticated user returns error', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $account = Account::factory()->withSlack()->create(['user_id' => $otherUser->id]);

    ShellServer::actingAs($user)
        ->tool(SendSlackMessageTool::class, ['account_id' => $account->id, 'channel_id' => 'C001', 'text' => 'Hello'])
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
        ->tool(SendSlackMessageTool::class, ['account_id' => $account->id, 'channel_id' => 'C001', 'text' => 'Hello'])
        ->assertHasErrors(['Slack is not connected for this account.']);
});

test('missing channel_id returns validation error', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withSlack()->create(['user_id' => $user->id]);

    ShellServer::actingAs($user)
        ->tool(SendSlackMessageTool::class, ['account_id' => $account->id, 'text' => 'Hello'])
        ->assertHasErrors();
});

test('missing text returns validation error', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withSlack()->create(['user_id' => $user->id]);

    ShellServer::actingAs($user)
        ->tool(SendSlackMessageTool::class, ['account_id' => $account->id, 'channel_id' => 'C001'])
        ->assertHasErrors();
});

test('happy path returns success message with ts', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withSlack()->create(['user_id' => $user->id]);

    $this->mock(SlackService::class, function ($mock) use ($account) {
        $mock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($arg) => $arg->id === $account->id)
            ->andReturn(['ok' => true, 'ts' => '1234567890.000100']);
    });

    ShellServer::actingAs($user)
        ->tool(SendSlackMessageTool::class, ['account_id' => $account->id, 'channel_id' => 'C001', 'text' => 'Hello world'])
        ->assertOk()
        ->assertSee('Message sent (ts: 1234567890.000100)');
});

test('service error returns mcp error response', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withSlack()->create(['user_id' => $user->id]);

    $this->mock(SlackService::class, function ($mock) {
        $mock->shouldReceive('sendMessage')
            ->once()
            ->andThrow(new \RuntimeException('channel_not_found'));
    });

    ShellServer::actingAs($user)
        ->tool(SendSlackMessageTool::class, ['account_id' => $account->id, 'channel_id' => 'C001', 'text' => 'Hello world'])
        ->assertHasErrors(['Slack API unavailable: channel_not_found']);
});
