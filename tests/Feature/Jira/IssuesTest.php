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
            'description' => 'Test issue description',
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
        'nextPageToken' => null,
        'isLast' => true,
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/jira/issues/search?jql=project=TEST", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonStructure(['data', 'meta' => ['next_page_token', 'is_last']]);
});

test('search issues includes correct meta values', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response([
        'issues' => [makeJiraIssueData()],
        'nextPageToken' => 'abc123cursor',
        'isLast' => false,
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/jira/issues/search?jql=project=TEST&maxResults=25", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonPath('meta.next_page_token', 'abc123cursor')
        ->assertJsonPath('meta.is_last', false);
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
        ->assertJsonPath('data.summary', 'Test issue')
        ->assertJsonPath('data.description', 'Test issue description');
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

test('search issues includes description in issue data', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response([
        'issues' => [makeJiraIssueData()],
        'nextPageToken' => null,
        'isLast' => true,
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/jira/issues/search?jql=project=TEST", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonPath('data.0.description', 'Test issue description');
});

test('create issue passes description to jira', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response(makeJiraIssueData(), 201)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->postJson("/api/{$account->id}/jira/issues", [
        'fields' => [
            'project' => ['key' => 'TEST'],
            'issuetype' => ['name' => 'Bug'],
            'summary' => 'Test issue',
            'description' => 'Test issue description',
        ],
    ], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertStatus(201)
        ->assertJsonPath('data.description', 'Test issue description');
});

test('update issue accepts description field', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response([], 204)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->putJson("/api/{$account->id}/jira/issues/TEST-1", [
        'fields' => [
            'description' => 'Updated description',
        ],
    ], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertStatus(204);
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
