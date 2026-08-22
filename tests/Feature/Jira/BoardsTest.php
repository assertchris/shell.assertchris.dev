<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function makeBoardData(array $overrides = []): array
{
    return array_merge([
        'id' => 1,
        'name' => 'My Scrum Board',
        'type' => 'scrum',
        'location' => [
            'projectKey' => 'PROJ',
            'projectName' => 'My Project',
        ],
    ], $overrides);
}

test('list boards returns board collection', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response([
        'values' => [makeBoardData()],
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/jira/boards", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'name', 'type']], 'meta' => ['total_count']]);
});

test('get board returns board data', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response(makeBoardData(), 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/jira/boards/1", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonPath('data.id', 1)
        ->assertJsonPath('data.name', 'My Scrum Board');
});

test('get board returns 404 when not found', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response([], 404)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/jira/boards/999", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});
