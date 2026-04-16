<?php

use App\Models\Account;
use App\Models\User;
use App\Services\SlackService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('send slack message requires authentication', function () {
    $account = Account::factory()->withSlack()->create();

    $this->postJson("/api/slack/{$account->id}/channels/C001/messages", ['text' => 'Hello'])
        ->assertUnauthorized();
});

test('send slack message returns 404 for other user account', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $account = Account::factory()->withSlack()->for($otherUser)->create();
    $token = $user->createToken('test');

    $this->postJson("/api/slack/{$account->id}/channels/C001/messages", ['text' => 'Hello'], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('send slack message returns 404 when slack not connected', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['slack_used' => false]);
    $token = $user->createToken('test');

    $this->postJson("/api/slack/{$account->id}/channels/C001/messages", ['text' => 'Hello'], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('send slack message returns 422 when text is missing', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withSlack()->for($user)->create();
    $token = $user->createToken('test');

    $this->postJson("/api/slack/{$account->id}/channels/C001/messages", [], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['text']);
});

test('send slack message returns ts on success', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withSlack()->for($user)->create();
    $token = $user->createToken('test');

    $this->mock(SlackService::class, function ($mock) use ($account) {
        $mock->shouldReceive('sendMessage')
            ->once()
            ->with(\Mockery::on(fn ($a) => $a->is($account)), 'C001', 'Hello world')
            ->andReturn(['ok' => true, 'ts' => '1234567890.000100']);
    });

    $this->postJson("/api/slack/{$account->id}/channels/C001/messages", ['text' => 'Hello world'], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertSuccessful()
        ->assertJsonPath('ts', '1234567890.000100');
});

test('send slack message returns 503 on service error', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withSlack()->for($user)->create();
    $token = $user->createToken('test');

    $this->mock(SlackService::class, function ($mock) {
        $mock->shouldReceive('sendMessage')
            ->once()
            ->andThrow(new \RuntimeException('message_too_long'));
    });

    $this->postJson("/api/slack/{$account->id}/channels/C001/messages", ['text' => 'Hello'], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertServiceUnavailable()
        ->assertJsonPath('error', 'Slack API unavailable: message_too_long');
});
