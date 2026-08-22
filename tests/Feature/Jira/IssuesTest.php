<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function makeJiraIssueData(array $overrides = []): array
{
    return array_merge([
        'id' => '10001',
        'key' => 'TEST-1',
        'fields' => [
            'summary' => 'Test issue',
            'status' => ['name' => 'Open'],
            'assignee' => null,
            'reporter' => null,
            'priority' => ['name' => 'Medium'],
            'issuetype' => ['name' => 'Bug'],
            'project' => ['key' => 'TEST', 'name' => 'Test Project'],
            'created' => '2024-01-01T00:00:00.000+0000',
            'updated' => '2024-01-02T00:00:00.000+0000',
            'labels' => [],
            'fixVersions' => [],
            'components' => [],
            'attachment' => [],
            'parent' => null,
            'subtasks' => [],
        ],
    ], $overrides);
}

test('search issues returns 200 with meta', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response([
        'issues' => [makeJiraIssueData()],
        'startAt' => 0,
        'maxResults' => 50,
        'total' => 1,
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/jira/issues/search?jql=project=TEST", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonStructure(['data', 'meta' => ['start_at', 'max_results', 'total']]);
});

test('search issues includes correct meta values', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response([
        'issues' => [makeJiraIssueData()],
        'startAt' => 10,
        'maxResults' => 25,
        'total' => 100,
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/jira/issues/search?jql=project=TEST&startAt=10&maxResults=25", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonPath('meta.start_at', 10)
        ->assertJsonPath('meta.max_results', 25)
        ->assertJsonPath('meta.total', 100);
});

test('get single issue returns issue data', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response(makeJiraIssueData(), 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/jira/issues/TEST-1", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonPath('data.key', 'TEST-1')
        ->assertJsonPath('data.summary', 'Test issue');
});

test('get issue returns 404 when not found', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response([], 404)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/jira/issues/MISSING-99", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('get issue comments returns 200', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response([
        'comments' => [
            ['id' => '1', 'author' => ['accountId' => 'abc', 'displayName' => 'Alice'], 'body' => [], 'created' => '2024-01-01T00:00:00Z', 'updated' => '2024-01-01T00:00:00Z'],
        ],
        'total' => 1,
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/jira/issues/TEST-1/comments", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk();
});

test('get issue worklogs returns 200', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response([
        'worklogs' => [
            ['id' => '1', 'author' => ['accountId' => 'abc', 'displayName' => 'Alice'], 'timeSpentSeconds' => 3600, 'started' => '2024-01-01T09:00:00Z', 'comment' => null],
        ],
        'total' => 1,
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/jira/issues/TEST-1/worklogs", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk();
});

test('get issue transitions returns 200', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response([
        'transitions' => [
            ['id' => '11', 'name' => 'To Do', 'to' => ['name' => 'To Do', 'statusCategory' => ['name' => 'To Do']]],
        ],
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/jira/issues/TEST-1/transitions", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk();
});

test('get issue changelog returns 200', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response([
        'values' => [
            ['id' => '1', 'author' => ['accountId' => 'abc', 'displayName' => 'Alice'], 'created' => '2024-01-01T00:00:00Z', 'items' => []],
        ],
        'startAt' => 0,
        'maxResults' => 50,
        'total' => 1,
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/jira/issues/TEST-1/changelog", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk();
});

test('get issue attachments returns 200', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response([
        'id' => '10001',
        'key' => 'TEST-1',
        'fields' => [
            'attachment' => [
                ['id' => '10001', 'filename' => 'screenshot.png', 'mimeType' => 'image/png', 'size' => 12345, 'content' => 'https://example.com/file.png', 'thumbnail' => null],
            ],
        ],
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/jira/issues/TEST-1/attachments", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk();
});

test('429 from jira issues propagates as 429 response with retry-after', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response([], 429, ['Retry-After' => '30'])]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $response = $this->getJson("/api/{$account->id}/jira/issues/search?jql=project=TEST", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ]);

    $response->assertStatus(429);
    expect($response->headers->get('Retry-After'))->toBe('30');
});
