<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function makeFilterData(array $overrides = []): array
{
    return array_merge([
        'id' => '10001',
        'name' => 'My Open Issues',
        'description' => 'All open issues assigned to me',
        'jql' => 'assignee = currentUser() AND status != Done',
        'owner' => ['accountId' => 'acc-1', 'displayName' => 'Alice'],
        'favourite' => true,
    ], $overrides);
}

test('list filters returns filter collection', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response([
        makeFilterData(),
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/jira/filters", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'name', 'jql']], 'meta' => ['total_count']]);
});

test('get filter returns filter data', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response(makeFilterData(), 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/jira/filters/10001", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonPath('data.id', '10001')
        ->assertJsonPath('data.name', 'My Open Issues');
});

test('get filter returns 404 when not found', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response([], 404)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/jira/filters/99999", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});
