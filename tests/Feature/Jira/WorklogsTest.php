<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function makeWorklogResponse(array $overrides = []): array
{
    return array_merge([
        'id' => '10001',
        'author' => ['accountId' => 'acc-1', 'displayName' => 'Alice'],
        'timeSpentSeconds' => 3600,
        'started' => '2024-01-01T09:00:00.000+0000',
        'comment' => null,
    ], $overrides);
}

test('add worklog with valid data returns 201', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response(makeWorklogResponse(), 201)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->postJson("/api/{$account->id}/jira/issues/PROJ-1/worklogs", [
        'time_spent' => '1h',
        'started' => '2024-01-01T09:00:00.000+0000',
    ], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertStatus(201)
        ->assertJsonStructure(['data' => ['id', 'author', 'time_spent_seconds']]);
});

test('add worklog returns 422 when time_spent is missing', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->postJson("/api/{$account->id}/jira/issues/PROJ-1/worklogs", [
        'started' => '2024-01-01T09:00:00.000+0000',
    ], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['time_spent']);
});

test('add worklog returns 422 when started is missing', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->postJson("/api/{$account->id}/jira/issues/PROJ-1/worklogs", [
        'time_spent' => '1h',
    ], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['started']);
});

test('update worklog with valid data returns 200', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response(makeWorklogResponse(), 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->putJson("/api/{$account->id}/jira/issues/PROJ-1/worklogs/10001", [
        'time_spent' => '2h',
        'started' => '2024-01-01T09:00:00.000+0000',
    ], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonStructure(['data' => ['id', 'author', 'time_spent_seconds']]);
});

test('delete worklog returns 204', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response(null, 204)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->deleteJson("/api/{$account->id}/jira/issues/PROJ-1/worklogs/10001", [], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertStatus(204);
});
