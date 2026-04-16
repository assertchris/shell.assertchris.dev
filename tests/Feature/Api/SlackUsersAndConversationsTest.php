<?php

use App\Models\Account;
use App\Models\User;
use App\Services\SlackService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('list slack users requires authentication', function () {
    $account = Account::factory()->withSlack()->create();

    $this->getJson("/api/slack/{$account->id}/users")
        ->assertUnauthorized();
});

test('list slack users returns 404 for other user account', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $account = Account::factory()->withSlack()->for($otherUser)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/slack/{$account->id}/users", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('list slack users returns 404 when slack not connected', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['slack_used' => false]);
    $token = $user->createToken('test');

    $this->getJson("/api/slack/{$account->id}/users", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('list slack users returns 200 with user list', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withSlack()->for($user)->create();
    $token = $user->createToken('test');

    $users = [
        ['id' => 'U001', 'name' => 'alice', 'real_name' => 'Alice Smith', 'is_bot' => false],
        ['id' => 'U002', 'name' => 'bob', 'real_name' => 'Bob Jones', 'is_bot' => false],
    ];

    $this->mock(SlackService::class, function ($mock) use ($account, $users) {
        $mock->shouldReceive('listUsers')
            ->once()
            ->with(\Mockery::on(fn ($a) => $a->is($account)), null)
            ->andReturn(['users' => $users, 'next_cursor' => null]);
    });

    $this->getJson("/api/slack/{$account->id}/users", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', 'U001')
        ->assertJsonPath('data.0.name', 'alice')
        ->assertJsonPath('data.1.id', 'U002')
        ->assertJsonPath('meta.next_cursor', null);
});

test('list slack users passes cursor query param to service', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withSlack()->for($user)->create();
    $token = $user->createToken('test');

    $this->mock(SlackService::class, function ($mock) use ($account) {
        $mock->shouldReceive('listUsers')
            ->once()
            ->with(\Mockery::on(fn ($a) => $a->is($account)), 'next-cursor')
            ->andReturn(['users' => [], 'next_cursor' => null]);
    });

    $this->getJson("/api/slack/{$account->id}/users?cursor=next-cursor", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertSuccessful();
});

test('list slack users returns 503 on service error', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withSlack()->for($user)->create();
    $token = $user->createToken('test');

    $this->mock(SlackService::class, function ($mock) {
        $mock->shouldReceive('listUsers')
            ->once()
            ->andThrow(new \RuntimeException('invalid_auth'));
    });

    $this->getJson("/api/slack/{$account->id}/users", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertServiceUnavailable()
        ->assertJsonPath('error', 'Slack API unavailable: invalid_auth');
});

test('open slack conversation requires authentication', function () {
    $account = Account::factory()->withSlack()->create();

    $this->postJson("/api/slack/{$account->id}/conversations/open", ['user_id' => 'U001'])
        ->assertUnauthorized();
});

test('open slack conversation returns 404 for other user account', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $account = Account::factory()->withSlack()->for($otherUser)->create();
    $token = $user->createToken('test');

    $this->postJson("/api/slack/{$account->id}/conversations/open", ['user_id' => 'U001'], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('open slack conversation returns 404 when slack not connected', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['slack_used' => false]);
    $token = $user->createToken('test');

    $this->postJson("/api/slack/{$account->id}/conversations/open", ['user_id' => 'U001'], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('open slack conversation returns 422 when user_id is missing', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withSlack()->for($user)->create();
    $token = $user->createToken('test');

    $this->mock(SlackService::class, function ($mock) {
        $mock->shouldNotReceive('openConversation');
    });

    $this->postJson("/api/slack/{$account->id}/conversations/open", [], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertUnprocessable();
});

test('open slack conversation returns channel_id on success', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withSlack()->for($user)->create();
    $token = $user->createToken('test');

    $this->mock(SlackService::class, function ($mock) use ($account) {
        $mock->shouldReceive('openConversation')
            ->once()
            ->with(\Mockery::on(fn ($a) => $a->is($account)), 'U001')
            ->andReturn(['channel_id' => 'D001ABCDE']);
    });

    $this->postJson("/api/slack/{$account->id}/conversations/open", ['user_id' => 'U001'], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertSuccessful()
        ->assertJsonPath('channel_id', 'D001ABCDE');
});

test('open slack conversation returns 503 on service error', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withSlack()->for($user)->create();
    $token = $user->createToken('test');

    $this->mock(SlackService::class, function ($mock) {
        $mock->shouldReceive('openConversation')
            ->once()
            ->andThrow(new \RuntimeException('user_not_found'));
    });

    $this->postJson("/api/slack/{$account->id}/conversations/open", ['user_id' => 'U_BAD'], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertServiceUnavailable()
        ->assertJsonPath('error', 'Slack API unavailable: user_not_found');
});
