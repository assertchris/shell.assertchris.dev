<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function makeProjectData(array $overrides = []): array
{
    return array_merge([
        'id' => '10000',
        'key' => 'PROJ',
        'name' => 'My Project',
        'description' => 'A test project',
        'projectTypeKey' => 'software',
        'style' => 'next-gen',
        'isPrivate' => false,
    ], $overrides);
}

test('list projects returns project collection with meta', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response([
        'values' => [makeProjectData()],
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/jira/projects", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'key', 'name']], 'meta' => ['total_count']]);
});

test('get project returns project data', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response(makeProjectData(), 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/jira/projects/PROJ", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonPath('data.key', 'PROJ');
});

test('get project returns 404 when not found', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response([], 404)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/jira/projects/MISSING", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('list project components returns collection', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response([
        ['id' => '1', 'name' => 'Backend'],
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/jira/projects/PROJ/components", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonStructure(['data', 'meta' => ['total_count']]);
});

test('list project versions returns collection', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response([
        ['id' => '1', 'name' => '1.0.0'],
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/jira/projects/PROJ/versions", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonStructure(['data', 'meta' => ['total_count']]);
});
