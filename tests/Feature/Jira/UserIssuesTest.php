<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function makeUserIssueResponse(array $overrides = []): array
{
    return array_merge([
        'issues' => [],
        'startAt' => 0,
        'maxResults' => 50,
        'total' => 0,
    ], $overrides);
}

test('my assigned issues returns 200 with meta', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response(makeUserIssueResponse(), 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/jira/user/issues", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonStructure(['data', 'meta' => ['start_at', 'max_results', 'total']]);
});

test('my reported issues returns 200 with meta', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response(makeUserIssueResponse(), 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/jira/user/reported", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonStructure(['data', 'meta' => ['start_at', 'max_results', 'total']]);
});

test('my watched issues returns 200 with meta', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response(makeUserIssueResponse(), 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/jira/user/watching", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonStructure(['data', 'meta' => ['start_at', 'max_results', 'total']]);
});

test('issues mentioning me returns 200 with meta', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response(makeUserIssueResponse(), 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/jira/user/mentions", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonStructure(['data', 'meta' => ['start_at', 'max_results', 'total']]);
});

test('issues with recent activity returns 200 with meta', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response(makeUserIssueResponse(), 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/jira/user/activity", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonStructure(['data', 'meta' => ['start_at', 'max_results', 'total']]);
});
