<?php

use App\Models\Account;
use App\Models\User;
use App\Services\SlackService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('list slack channels requires authentication', function () {
    $account = Account::factory()->withSlack()->create();

    $this->getJson("/api/slack/{$account->id}/channels")
        ->assertUnauthorized();
});

test('list slack channels returns 404 for other user account', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $account = Account::factory()->withSlack()->for($otherUser)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/slack/{$account->id}/channels", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('list slack channels returns 404 when slack not connected', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['slack_used' => false]);
    $token = $user->createToken('test');

    $this->getJson("/api/slack/{$account->id}/channels", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('list slack channels returns 200 with channel list', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withSlack()->for($user)->create();
    $token = $user->createToken('test');

    $channels = [
        ['id' => 'C001', 'name' => 'general', 'is_private' => false, 'num_members' => 50],
        ['id' => 'C002', 'name' => 'secret', 'is_private' => true, 'num_members' => 3],
    ];

    $this->mock(SlackService::class, function ($mock) use ($account, $channels) {
        $mock->shouldReceive('listChannels')
            ->once()
            ->with(\Mockery::on(fn ($a) => $a->is($account)), null)
            ->andReturn($channels);
    });

    $this->getJson("/api/slack/{$account->id}/channels", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', 'C001')
        ->assertJsonPath('data.0.name', 'general')
        ->assertJsonPath('data.1.id', 'C002')
        ->assertJsonPath('data.1.name', 'secret');
});

test('list slack channels passes cursor query param to service', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withSlack()->for($user)->create();
    $token = $user->createToken('test');

    $this->mock(SlackService::class, function ($mock) use ($account) {
        $mock->shouldReceive('listChannels')
            ->once()
            ->with(\Mockery::on(fn ($a) => $a->is($account)), 'next-cursor')
            ->andReturn([]);
    });

    $this->getJson("/api/slack/{$account->id}/channels?cursor=next-cursor", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertSuccessful();
});

test('list slack channels returns 503 on service error', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withSlack()->for($user)->create();
    $token = $user->createToken('test');

    $this->mock(SlackService::class, function ($mock) {
        $mock->shouldReceive('listChannels')
            ->once()
            ->andThrow(new \RuntimeException('not_authed'));
    });

    $this->getJson("/api/slack/{$account->id}/channels", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertServiceUnavailable()
        ->assertJsonPath('error', 'Slack API unavailable: not_authed');
});

test('list slack messages requires authentication', function () {
    $account = Account::factory()->withSlack()->create();

    $this->getJson("/api/slack/{$account->id}/channels/C001/messages")
        ->assertUnauthorized();
});

test('list slack messages returns 404 for other user account', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $account = Account::factory()->withSlack()->for($otherUser)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/slack/{$account->id}/channels/C001/messages", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('list slack messages returns 404 when slack not connected', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['slack_used' => false]);
    $token = $user->createToken('test');

    $this->getJson("/api/slack/{$account->id}/channels/C001/messages", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('list slack messages returns 200 with messages and next cursor', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withSlack()->for($user)->create();
    $token = $user->createToken('test');

    $messages = [
        ['ts' => '1234567890.000100', 'user' => 'U001', 'text' => 'Hello world'],
        ['ts' => '1234567890.000200', 'user' => 'U002', 'text' => 'Hi there'],
    ];

    $this->mock(SlackService::class, function ($mock) use ($account, $messages) {
        $mock->shouldReceive('listMessages')
            ->once()
            ->with(\Mockery::on(fn ($a) => $a->is($account)), 'C001', 50, null)
            ->andReturn(['messages' => $messages, 'next_cursor' => 'cursor-abc']);
    });

    $this->getJson("/api/slack/{$account->id}/channels/C001/messages", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.ts', '1234567890.000100')
        ->assertJsonPath('data.0.user', 'U001')
        ->assertJsonPath('data.0.text', 'Hello world')
        ->assertJsonPath('meta.next_cursor', 'cursor-abc');
});

test('list slack messages returns 503 on service error', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withSlack()->for($user)->create();
    $token = $user->createToken('test');

    $this->mock(SlackService::class, function ($mock) {
        $mock->shouldReceive('listMessages')
            ->once()
            ->andThrow(new \RuntimeException('channel_not_found'));
    });

    $this->getJson("/api/slack/{$account->id}/channels/C001/messages", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertServiceUnavailable()
        ->assertJsonPath('error', 'Slack API unavailable: channel_not_found');
});
