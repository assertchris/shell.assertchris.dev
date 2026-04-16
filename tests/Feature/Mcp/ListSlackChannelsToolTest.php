<?php

use App\Mcp\Servers\ShellServer;
use App\Mcp\Tools\ListSlackChannelsTool;
use App\Models\Account;
use App\Models\User;
use App\Services\SlackService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('unauthenticated request returns error', function () {
    $account = Account::factory()->withSlack()->create();

    ShellServer::tool(ListSlackChannelsTool::class, ['account_id' => $account->id])
        ->assertHasErrors(['Account not found.']);
});

test('account not owned by authenticated user returns error', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $account = Account::factory()->withSlack()->create(['user_id' => $otherUser->id]);

    ShellServer::actingAs($user)
        ->tool(ListSlackChannelsTool::class, ['account_id' => $account->id])
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
        ->tool(ListSlackChannelsTool::class, ['account_id' => $account->id])
        ->assertHasErrors(['Slack is not connected for this account.']);
});

test('happy path returns channel list', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withSlack()->create(['user_id' => $user->id]);

    $channels = [
        ['id' => 'C001', 'name' => 'general', 'is_private' => false, 'num_members' => 10],
        ['id' => 'C002', 'name' => 'random', 'is_private' => false, 'num_members' => 5],
    ];

    $this->mock(SlackService::class, function ($mock) use ($account, $channels) {
        $mock->shouldReceive('listChannels')
            ->once()
            ->withArgs(fn ($arg) => $arg->id === $account->id)
            ->andReturn($channels);
    });

    ShellServer::actingAs($user)
        ->tool(ListSlackChannelsTool::class, ['account_id' => $account->id])
        ->assertOk()
        ->assertSee('general')
        ->assertSee('C001');
});
