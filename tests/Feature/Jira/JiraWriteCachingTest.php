<?php

use App\Models\Account;
use App\Models\User;
use App\Support\CacheKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function jiraAuth(): array
{
    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    return [$user, $account, $token->plainTextToken];
}

function jiraHeaders(string $token): array
{
    return ['Authorization' => 'Bearer '.$token];
}

test('updateIssue busts issue and user-assigned and user-activity cache keys', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response(null, 204)]);

    [, $account, $token] = jiraAuth();
    $key = 'PROJ-1';

    $issueKey = CacheKey::for('jira', $account->id, 'issue', $key);
    $assignedKey = CacheKey::hashed([], 'jira', $account->id, 'user', 'assigned');
    $activityKey = CacheKey::hashed([], 'jira', $account->id, 'user', 'activity');

    Cache::put($issueKey, ['id' => '10000'], now()->addMinutes(5));
    Cache::put($assignedKey, [], now()->addMinutes(5));
    Cache::put($activityKey, [], now()->addMinutes(5));

    $adf = ['type' => 'doc', 'version' => 1, 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Updated']]]]];

    $this->putJson("/api/{$account->id}/jira/issues/{$key}", ['fields' => ['description' => $adf]], jiraHeaders($token))
        ->assertNoContent();

    expect(Cache::missing($issueKey))->toBeTrue();
    expect(Cache::missing($assignedKey))->toBeTrue();
    expect(Cache::missing($activityKey))->toBeTrue();
});

test('transitionIssue busts issue and issue transitions cache keys', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response(null, 204)]);

    [, $account, $token] = jiraAuth();
    $key = 'PROJ-2';

    $issueKey = CacheKey::for('jira', $account->id, 'issue', $key);
    $transitionsKey = CacheKey::for('jira', $account->id, 'issue', $key, 'transitions');

    Cache::put($issueKey, ['id' => '10001'], now()->addMinutes(5));
    Cache::put($transitionsKey, [], now()->addMinutes(5));

    $this->postJson("/api/{$account->id}/jira/issues/{$key}/transitions", ['transition_id' => '31'], jiraHeaders($token))
        ->assertNoContent();

    expect(Cache::missing($issueKey))->toBeTrue();
    expect(Cache::missing($transitionsKey))->toBeTrue();
});

test('assignIssue busts issue and user-assigned cache keys', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response(null, 204)]);

    [, $account, $token] = jiraAuth();
    $key = 'PROJ-3';

    $issueKey = CacheKey::for('jira', $account->id, 'issue', $key);
    $assignedKey = CacheKey::hashed([], 'jira', $account->id, 'user', 'assigned');

    Cache::put($issueKey, ['id' => '10002'], now()->addMinutes(5));
    Cache::put($assignedKey, [], now()->addMinutes(5));

    $this->putJson("/api/{$account->id}/jira/issues/{$key}/assignee", ['account_id' => 'acc-456'], jiraHeaders($token))
        ->assertNoContent();

    expect(Cache::missing($issueKey))->toBeTrue();
    expect(Cache::missing($assignedKey))->toBeTrue();
});

test('addComment busts issue comments cache key', function () {
    $body = ['type' => 'doc', 'version' => 1, 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hi']]]]];
    Http::fake(['https://test.atlassian.net/*' => Http::response(['id' => '20001', 'author' => ['accountId' => 'a', 'displayName' => 'A'], 'body' => $body, 'created' => '2026-01-01T00:00:00.000+0000', 'updated' => '2026-01-01T00:00:00.000+0000'], 201)]);

    [, $account, $token] = jiraAuth();
    $key = 'PROJ-4';

    $commentsKey = CacheKey::for('jira', $account->id, 'issue', $key, 'comments');
    Cache::put($commentsKey, [], now()->addMinutes(5));

    $this->postJson("/api/{$account->id}/jira/issues/{$key}/comments", ['body' => $body], jiraHeaders($token))
        ->assertStatus(201);

    expect(Cache::missing($commentsKey))->toBeTrue();
});

test('updateComment busts issue comments cache key', function () {
    $body = ['type' => 'doc', 'version' => 1, 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hi']]]]];
    Http::fake(['https://test.atlassian.net/*' => Http::response(['id' => '20002', 'author' => ['accountId' => 'a', 'displayName' => 'A'], 'body' => $body, 'created' => '2026-01-01T00:00:00.000+0000', 'updated' => '2026-01-01T00:00:00.000+0000'], 200)]);

    [, $account, $token] = jiraAuth();
    $key = 'PROJ-5';

    $commentsKey = CacheKey::for('jira', $account->id, 'issue', $key, 'comments');
    Cache::put($commentsKey, [], now()->addMinutes(5));

    $this->putJson("/api/{$account->id}/jira/issues/{$key}/comments/20002", ['body' => $body], jiraHeaders($token))
        ->assertOk();

    expect(Cache::missing($commentsKey))->toBeTrue();
});

test('deleteComment busts issue comments cache key', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response(null, 204)]);

    [, $account, $token] = jiraAuth();
    $key = 'PROJ-6';

    $commentsKey = CacheKey::for('jira', $account->id, 'issue', $key, 'comments');
    Cache::put($commentsKey, [], now()->addMinutes(5));

    $this->deleteJson("/api/{$account->id}/jira/issues/{$key}/comments/20003", [], jiraHeaders($token))
        ->assertNoContent();

    expect(Cache::missing($commentsKey))->toBeTrue();
});

test('addWorklog busts issue worklogs cache key', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response(['id' => '30001', 'author' => ['accountId' => 'a', 'displayName' => 'A'], 'timeSpent' => '1h', 'started' => '2026-10-10T09:00:00.000+0000', 'created' => '2026-10-10T09:00:00.000+0000', 'updated' => '2026-10-10T09:00:00.000+0000'], 201)]);

    [, $account, $token] = jiraAuth();
    $key = 'PROJ-7';

    $worklogsKey = CacheKey::for('jira', $account->id, 'issue', $key, 'worklogs');
    Cache::put($worklogsKey, [], now()->addMinutes(5));

    $this->postJson("/api/{$account->id}/jira/issues/{$key}/worklogs", ['time_spent' => '1h', 'started' => '2026-10-10T09:00:00.000+0000'], jiraHeaders($token))
        ->assertStatus(201);

    expect(Cache::missing($worklogsKey))->toBeTrue();
});

test('deleteWorklog busts issue worklogs cache key', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response(null, 204)]);

    [, $account, $token] = jiraAuth();
    $key = 'PROJ-8';

    $worklogsKey = CacheKey::for('jira', $account->id, 'issue', $key, 'worklogs');
    Cache::put($worklogsKey, [], now()->addMinutes(5));

    $this->deleteJson("/api/{$account->id}/jira/issues/{$key}/worklogs/30002", [], jiraHeaders($token))
        ->assertNoContent();

    expect(Cache::missing($worklogsKey))->toBeTrue();
});

test('createIssue busts assigned reported and activity cache keys', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response(['id' => '40001', 'key' => 'PROJ-9', 'fields' => ['summary' => 'New', 'status' => ['name' => 'Open'], 'issuetype' => ['name' => 'Task'], 'priority' => null, 'assignee' => null, 'reporter' => null, 'created' => '2026-01-01T00:00:00.000+0000', 'updated' => '2026-01-01T00:00:00.000+0000', 'description' => null, 'labels' => [], 'project' => ['key' => 'PROJ', 'name' => 'Project']]], 201)]);

    [, $account, $token] = jiraAuth();

    $assignedKey = CacheKey::hashed([], 'jira', $account->id, 'user', 'assigned');
    $reportedKey = CacheKey::hashed([], 'jira', $account->id, 'user', 'reported');
    $activityKey = CacheKey::hashed([], 'jira', $account->id, 'user', 'activity');

    Cache::put($assignedKey, [], now()->addMinutes(5));
    Cache::put($reportedKey, [], now()->addMinutes(5));
    Cache::put($activityKey, [], now()->addMinutes(5));

    $this->postJson("/api/{$account->id}/jira/issues", [
        'fields' => [
            'project' => ['key' => 'PROJ'],
            'issuetype' => ['name' => 'Task'],
            'summary' => 'New issue',
        ],
    ], jiraHeaders($token))->assertStatus(201);

    expect(Cache::missing($assignedKey))->toBeTrue();
    expect(Cache::missing($reportedKey))->toBeTrue();
    expect(Cache::missing($activityKey))->toBeTrue();
});

test('deleteIssue busts issue and all three user view cache keys', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response(null, 204)]);

    [, $account, $token] = jiraAuth();
    $key = 'PROJ-10';

    $issueKey = CacheKey::for('jira', $account->id, 'issue', $key);
    $assignedKey = CacheKey::hashed([], 'jira', $account->id, 'user', 'assigned');
    $reportedKey = CacheKey::hashed([], 'jira', $account->id, 'user', 'reported');
    $activityKey = CacheKey::hashed([], 'jira', $account->id, 'user', 'activity');

    Cache::put($issueKey, ['id' => '10010'], now()->addMinutes(5));
    Cache::put($assignedKey, [], now()->addMinutes(5));
    Cache::put($reportedKey, [], now()->addMinutes(5));
    Cache::put($activityKey, [], now()->addMinutes(5));

    $this->deleteJson("/api/{$account->id}/jira/issues/{$key}", [], jiraHeaders($token))
        ->assertNoContent();

    expect(Cache::missing($issueKey))->toBeTrue();
    expect(Cache::missing($assignedKey))->toBeTrue();
    expect(Cache::missing($reportedKey))->toBeTrue();
    expect(Cache::missing($activityKey))->toBeTrue();
});

test('moveIssuesToSprint busts sprint issues cache key', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response(null, 204)]);

    [, $account, $token] = jiraAuth();
    $sprintId = 42;

    $sprintKey = CacheKey::hashed([], 'jira', $account->id, 'sprint', $sprintId, 'issues');
    Cache::put($sprintKey, [], now()->addMinutes(5));

    $this->postJson("/api/{$account->id}/jira/sprints/{$sprintId}/issues", ['issue_keys' => ['PROJ-1', 'PROJ-2']], jiraHeaders($token))
        ->assertNoContent();

    expect(Cache::missing($sprintKey))->toBeTrue();
});
