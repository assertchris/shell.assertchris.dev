<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function makeSprintData(array $overrides = []): array
{
    return array_merge([
        'id' => 1,
        'name' => 'Sprint 1',
        'state' => 'active',
        'startDate' => '2024-01-01T00:00:00.000Z',
        'endDate' => '2024-01-14T00:00:00.000Z',
        'goal' => 'Ship the feature',
    ], $overrides);
}

test('list board sprints returns collection with meta', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response([
        'values' => [makeSprintData()],
        'startAt' => 0,
        'maxResults' => 50,
        'total' => 1,
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/jira/boards/1/sprints", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'name', 'state']], 'meta' => ['start_at', 'max_results', 'total']]);
});

test('get sprint returns sprint data', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response(makeSprintData(), 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/jira/sprints/1", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonPath('data.id', 1)
        ->assertJsonPath('data.name', 'Sprint 1');
});

test('get sprint returns 404 when not found', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response([], 404)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/jira/sprints/999", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('list sprint issues returns collection with meta', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response([
        'issues' => [],
        'startAt' => 0,
        'maxResults' => 50,
        'total' => 0,
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/jira/sprints/1/issues", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonStructure(['data', 'meta' => ['start_at', 'max_results', 'total']]);
});
