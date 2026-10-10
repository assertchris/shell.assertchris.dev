<?php

use AC\LaravelTaggedCache\TaggedCache;
use App\Models\Account;
use App\Models\User;
use App\Services\CalendarService;
use App\Services\SlackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('cache.default', 'array');
});

function responderAuth(string $service = 'jira'): array
{
    $user = User::factory()->create();
    $account = match ($service) {
        'calendar' => Account::factory()->withGoogleCalendar()->for($user)->create(),
        'slack' => Account::factory()->withSlack()->for($user)->create(),
        default => Account::factory()->withJira()->for($user)->create(),
    };
    $token = $user->createToken('test');

    return [$user, $account, $token->plainTextToken];
}

function responderHeaders(string $token): array
{
    return ['Authorization' => 'Bearer '.$token];
}

// ListAccountsResponder

test('listAccounts caches with account:user tag so AccountObserver flush covers it', function () {
    [$user, , $token] = responderAuth();

    $this->getJson('/api/accounts', responderHeaders($token))
        ->assertSuccessful();

    $userId = $user->id;

    $cached = TaggedCache::for(
        tags: ['accounts', "account:user:{$userId}"],
        context: ['userId' => $userId],
    )->get();

    expect($cached)->not->toBeNull();

    TaggedCache::for(tags: ["account:user:{$userId}"])->flush();

    $cleared = TaggedCache::for(
        tags: ['accounts', "account:user:{$userId}"],
        context: ['userId' => $userId],
    )->get();

    expect($cleared)->toBeNull();
});

// CreateCalendarEntryResponder

test('createCalendarEntry flushes calendar account tag', function () {
    [, $account, $token] = responderAuth('calendar');

    TaggedCache::for(
        tags: ['calendar', "calendar:{$account->id}"],
        context: ['type' => 'entries'],
    )->put(['items' => []], now()->addMinutes(5));

    expect(
        TaggedCache::for(tags: ['calendar', "calendar:{$account->id}"], context: ['type' => 'entries'])->get()
    )->not->toBeNull();

    $created = [
        'id' => 'evt-new',
        'title' => 'Test',
        'start' => '2026-10-10T09:00:00+00:00',
        'end' => '2026-10-10T10:00:00+00:00',
        'location' => null,
        'description' => null,
        'attendees' => [],
        'html_link' => 'https://calendar.google.com/event?eid=test',
    ];

    $this->mock(CalendarService::class, function ($mock) use ($created) {
        $mock->shouldReceive('createEntry')->once()->andReturn($created);
    });

    $this->postJson("/api/{$account->id}/calendar", [
        'summary' => 'Test',
        'start' => ['dateTime' => '2026-10-10T09:00:00+00:00', 'timeZone' => 'UTC'],
        'end' => ['dateTime' => '2026-10-10T10:00:00+00:00', 'timeZone' => 'UTC'],
    ], responderHeaders($token))->assertCreated();

    expect(
        TaggedCache::for(tags: ['calendar', "calendar:{$account->id}"], context: ['type' => 'entries'])->get()
    )->toBeNull();
});

// SendSlackMessageResponder

test('sendSlackMessage flushes channel-scoped slack tag', function () {
    [, $account, $token] = responderAuth('slack');

    TaggedCache::for(
        tags: ['slack', "slack:{$account->id}", "slack:{$account->id}:channel:C001"],
        context: ['type' => 'messages', 'channelId' => 'C001'],
    )->put(['messages' => []], now()->addMinutes(5));

    expect(
        TaggedCache::for(tags: ['slack', "slack:{$account->id}", "slack:{$account->id}:channel:C001"], context: ['type' => 'messages', 'channelId' => 'C001'])->get()
    )->not->toBeNull();

    $this->mock(SlackService::class, function ($mock) {
        $mock->shouldReceive('sendMessage')->once()->andReturn(['ok' => true, 'ts' => '1234567890.000100']);
    });

    $this->postJson("/api/slack/{$account->id}/channels/C001/messages", ['text' => 'Hi'], responderHeaders($token))
        ->assertSuccessful();

    expect(
        TaggedCache::for(tags: ['slack', "slack:{$account->id}", "slack:{$account->id}:channel:C001"], context: ['type' => 'messages', 'channelId' => 'C001'])->get()
    )->toBeNull();
});

// Jira AddCommentResponder

test('addComment flushes issue-scoped tag', function () {
    [, $account, $token] = responderAuth();
    $body = ['type' => 'doc', 'version' => 1, 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hi']]]]];

    Http::fake(['https://test.atlassian.net/*' => Http::response(['id' => '20001', 'author' => ['accountId' => 'a', 'displayName' => 'A'], 'body' => $body, 'created' => '2026-01-01T00:00:00.000+0000', 'updated' => '2026-01-01T00:00:00.000+0000'], 201)]);

    TaggedCache::for(
        tags: ['jira', "jira:{$account->id}", "jira:{$account->id}:issue:PROJ-1"],
        context: ['type' => 'comments', 'key' => 'PROJ-1'],
    )->put([], now()->addMinutes(5));

    expect(
        TaggedCache::for(tags: ['jira', "jira:{$account->id}", "jira:{$account->id}:issue:PROJ-1"], context: ['type' => 'comments', 'key' => 'PROJ-1'])->get()
    )->not->toBeNull();

    $this->postJson("/api/{$account->id}/jira/issues/PROJ-1/comments", ['body' => $body], responderHeaders($token))
        ->assertStatus(201);

    expect(
        TaggedCache::for(tags: ['jira', "jira:{$account->id}", "jira:{$account->id}:issue:PROJ-1"], context: ['type' => 'comments', 'key' => 'PROJ-1'])->get()
    )->toBeNull();
});

// Jira UpdateCommentResponder

test('updateComment flushes issue-scoped tag', function () {
    [, $account, $token] = responderAuth();
    $body = ['type' => 'doc', 'version' => 1, 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Updated']]]]];

    Http::fake(['https://test.atlassian.net/*' => Http::response(['id' => '20002', 'author' => ['accountId' => 'a', 'displayName' => 'A'], 'body' => $body, 'created' => '2026-01-01T00:00:00.000+0000', 'updated' => '2026-01-01T00:00:00.000+0000'], 200)]);

    TaggedCache::for(
        tags: ['jira', "jira:{$account->id}", "jira:{$account->id}:issue:PROJ-2"],
        context: ['type' => 'comments', 'key' => 'PROJ-2'],
    )->put([], now()->addMinutes(5));

    $this->putJson("/api/{$account->id}/jira/issues/PROJ-2/comments/20002", ['body' => $body], responderHeaders($token))
        ->assertOk();

    expect(
        TaggedCache::for(tags: ['jira', "jira:{$account->id}", "jira:{$account->id}:issue:PROJ-2"], context: ['type' => 'comments', 'key' => 'PROJ-2'])->get()
    )->toBeNull();
});

// Jira DeleteCommentResponder

test('deleteComment flushes issue-scoped tag', function () {
    [, $account, $token] = responderAuth();

    Http::fake(['https://test.atlassian.net/*' => Http::response(null, 204)]);

    TaggedCache::for(
        tags: ['jira', "jira:{$account->id}", "jira:{$account->id}:issue:PROJ-3"],
        context: ['type' => 'comments', 'key' => 'PROJ-3'],
    )->put([], now()->addMinutes(5));

    $this->deleteJson("/api/{$account->id}/jira/issues/PROJ-3/comments/20003", [], responderHeaders($token))
        ->assertNoContent();

    expect(
        TaggedCache::for(tags: ['jira', "jira:{$account->id}", "jira:{$account->id}:issue:PROJ-3"], context: ['type' => 'comments', 'key' => 'PROJ-3'])->get()
    )->toBeNull();
});

// Jira AddWorklogResponder

test('addWorklog flushes issue-scoped tag', function () {
    [, $account, $token] = responderAuth();

    Http::fake(['https://test.atlassian.net/*' => Http::response(['id' => '30001', 'author' => ['accountId' => 'a', 'displayName' => 'A'], 'timeSpent' => '1h', 'started' => '2026-10-10T09:00:00.000+0000', 'created' => '2026-10-10T09:00:00.000+0000', 'updated' => '2026-10-10T09:00:00.000+0000'], 201)]);

    TaggedCache::for(
        tags: ['jira', "jira:{$account->id}", "jira:{$account->id}:issue:PROJ-4"],
        context: ['type' => 'worklogs', 'key' => 'PROJ-4'],
    )->put([], now()->addMinutes(5));

    $this->postJson("/api/{$account->id}/jira/issues/PROJ-4/worklogs", ['time_spent' => '1h', 'started' => '2026-10-10T09:00:00.000+0000'], responderHeaders($token))
        ->assertStatus(201);

    expect(
        TaggedCache::for(tags: ['jira', "jira:{$account->id}", "jira:{$account->id}:issue:PROJ-4"], context: ['type' => 'worklogs', 'key' => 'PROJ-4'])->get()
    )->toBeNull();
});

// Jira DeleteWorklogResponder

test('deleteWorklog flushes issue-scoped tag', function () {
    [, $account, $token] = responderAuth();

    Http::fake(['https://test.atlassian.net/*' => Http::response(null, 204)]);

    TaggedCache::for(
        tags: ['jira', "jira:{$account->id}", "jira:{$account->id}:issue:PROJ-5"],
        context: ['type' => 'worklogs', 'key' => 'PROJ-5'],
    )->put([], now()->addMinutes(5));

    $this->deleteJson("/api/{$account->id}/jira/issues/PROJ-5/worklogs/30002", [], responderHeaders($token))
        ->assertNoContent();

    expect(
        TaggedCache::for(tags: ['jira', "jira:{$account->id}", "jira:{$account->id}:issue:PROJ-5"], context: ['type' => 'worklogs', 'key' => 'PROJ-5'])->get()
    )->toBeNull();
});

// Jira UpdateWorklogResponder

test('updateWorklog flushes issue-scoped tag', function () {
    [, $account, $token] = responderAuth();

    Http::fake(['https://test.atlassian.net/*' => Http::response(['id' => '30003', 'author' => ['accountId' => 'a', 'displayName' => 'A'], 'timeSpent' => '2h', 'started' => '2026-10-10T09:00:00.000+0000', 'created' => '2026-10-10T09:00:00.000+0000', 'updated' => '2026-10-10T09:00:00.000+0000'], 200)]);

    TaggedCache::for(
        tags: ['jira', "jira:{$account->id}", "jira:{$account->id}:issue:PROJ-6"],
        context: ['type' => 'worklogs', 'key' => 'PROJ-6'],
    )->put([], now()->addMinutes(5));

    $this->putJson("/api/{$account->id}/jira/issues/PROJ-6/worklogs/30003", ['time_spent' => '2h', 'started' => '2026-10-10T09:00:00.000+0000'], responderHeaders($token))
        ->assertOk();

    expect(
        TaggedCache::for(tags: ['jira', "jira:{$account->id}", "jira:{$account->id}:issue:PROJ-6"], context: ['type' => 'worklogs', 'key' => 'PROJ-6'])->get()
    )->toBeNull();
});

// Jira AssignIssueResponder

test('assignIssue flushes both issue-scoped and account-scoped tags', function () {
    [, $account, $token] = responderAuth();

    Http::fake(['https://test.atlassian.net/*' => Http::response(null, 204)]);

    TaggedCache::for(
        tags: ['jira', "jira:{$account->id}", "jira:{$account->id}:issue:PROJ-6"],
        context: ['type' => 'issue', 'key' => 'PROJ-6'],
    )->put(['key' => 'PROJ-6'], now()->addMinutes(5));

    TaggedCache::for(
        tags: ['jira', "jira:{$account->id}"],
        context: ['type' => 'myself'],
    )->put(['accountId' => 'abc'], now()->addMinutes(5));

    $this->putJson("/api/{$account->id}/jira/issues/PROJ-6/assignee", ['account_id' => 'acc-123'], responderHeaders($token))
        ->assertNoContent();

    expect(
        TaggedCache::for(tags: ['jira', "jira:{$account->id}", "jira:{$account->id}:issue:PROJ-6"], context: ['type' => 'issue', 'key' => 'PROJ-6'])->get()
    )->toBeNull();

    expect(
        TaggedCache::for(tags: ['jira', "jira:{$account->id}"], context: ['type' => 'myself'])->get()
    )->toBeNull();
});

// Jira TransitionIssueResponder

test('transitionIssue flushes issue-scoped tag', function () {
    [, $account, $token] = responderAuth();

    Http::fake(['https://test.atlassian.net/*' => Http::response(null, 204)]);

    TaggedCache::for(
        tags: ['jira', "jira:{$account->id}", "jira:{$account->id}:issue:PROJ-7"],
        context: ['type' => 'issue', 'key' => 'PROJ-7'],
    )->put(['key' => 'PROJ-7'], now()->addMinutes(5));

    $this->postJson("/api/{$account->id}/jira/issues/PROJ-7/transitions", ['transition_id' => '31'], responderHeaders($token))
        ->assertNoContent();

    expect(
        TaggedCache::for(tags: ['jira', "jira:{$account->id}", "jira:{$account->id}:issue:PROJ-7"], context: ['type' => 'issue', 'key' => 'PROJ-7'])->get()
    )->toBeNull();
});

// Jira UpdateIssueResponder

test('updateIssue flushes both issue-scoped and account-scoped tags', function () {
    [, $account, $token] = responderAuth();

    Http::fake(['https://test.atlassian.net/*' => Http::response(null, 204)]);

    TaggedCache::for(
        tags: ['jira', "jira:{$account->id}", "jira:{$account->id}:issue:PROJ-8"],
        context: ['type' => 'issue', 'key' => 'PROJ-8'],
    )->put(['key' => 'PROJ-8'], now()->addMinutes(5));

    TaggedCache::for(
        tags: ['jira', "jira:{$account->id}"],
        context: ['type' => 'myself'],
    )->put(['accountId' => 'abc'], now()->addMinutes(5));

    $adf = ['type' => 'doc', 'version' => 1, 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Updated']]]]];

    $this->putJson("/api/{$account->id}/jira/issues/PROJ-8", ['fields' => ['description' => $adf]], responderHeaders($token))
        ->assertNoContent();

    expect(
        TaggedCache::for(tags: ['jira', "jira:{$account->id}", "jira:{$account->id}:issue:PROJ-8"], context: ['type' => 'issue', 'key' => 'PROJ-8'])->get()
    )->toBeNull();

    expect(
        TaggedCache::for(tags: ['jira', "jira:{$account->id}"], context: ['type' => 'myself'])->get()
    )->toBeNull();
});

// Jira CreateIssueResponder

test('createIssue flushes account-scoped tag', function () {
    [, $account, $token] = responderAuth();

    Http::fake(['https://test.atlassian.net/*' => Http::response(['id' => '40001', 'key' => 'PROJ-9', 'fields' => ['summary' => 'New', 'status' => ['name' => 'Open'], 'issuetype' => ['name' => 'Task'], 'priority' => null, 'assignee' => null, 'reporter' => null, 'created' => '2026-01-01T00:00:00.000+0000', 'updated' => '2026-01-01T00:00:00.000+0000', 'description' => null, 'labels' => [], 'project' => ['key' => 'PROJ', 'name' => 'Project']]], 201)]);

    TaggedCache::for(
        tags: ['jira', "jira:{$account->id}"],
        context: ['type' => 'myself'],
    )->put(['accountId' => 'abc'], now()->addMinutes(5));

    $this->postJson("/api/{$account->id}/jira/issues", [
        'fields' => [
            'project' => ['key' => 'PROJ'],
            'issuetype' => ['name' => 'Task'],
            'summary' => 'New issue',
        ],
    ], responderHeaders($token))->assertStatus(201);

    expect(
        TaggedCache::for(tags: ['jira', "jira:{$account->id}"], context: ['type' => 'myself'])->get()
    )->toBeNull();
});

// Jira DeleteIssueResponder

test('deleteIssue flushes both issue-scoped and account-scoped tags', function () {
    [, $account, $token] = responderAuth();

    Http::fake(['https://test.atlassian.net/*' => Http::response(null, 204)]);

    TaggedCache::for(
        tags: ['jira', "jira:{$account->id}", "jira:{$account->id}:issue:PROJ-10"],
        context: ['type' => 'issue', 'key' => 'PROJ-10'],
    )->put(['key' => 'PROJ-10'], now()->addMinutes(5));

    TaggedCache::for(
        tags: ['jira', "jira:{$account->id}"],
        context: ['type' => 'myself'],
    )->put(['accountId' => 'abc'], now()->addMinutes(5));

    $this->deleteJson("/api/{$account->id}/jira/issues/PROJ-10", [], responderHeaders($token))
        ->assertNoContent();

    expect(
        TaggedCache::for(tags: ['jira', "jira:{$account->id}", "jira:{$account->id}:issue:PROJ-10"], context: ['type' => 'issue', 'key' => 'PROJ-10'])->get()
    )->toBeNull();

    expect(
        TaggedCache::for(tags: ['jira', "jira:{$account->id}"], context: ['type' => 'myself'])->get()
    )->toBeNull();
});

// Jira MoveIssuesToSprintResponder

test('moveIssuesToSprint flushes sprint-scoped tag', function () {
    [, $account, $token] = responderAuth();

    Http::fake(['https://test.atlassian.net/*' => Http::response(null, 204)]);

    TaggedCache::for(
        tags: ['jira', "jira:{$account->id}", "jira:{$account->id}:sprint:42"],
        context: ['type' => 'sprint-issues', 'sprintId' => 42],
    )->put(['issues' => []], now()->addMinutes(5));

    expect(
        TaggedCache::for(tags: ['jira', "jira:{$account->id}", "jira:{$account->id}:sprint:42"], context: ['type' => 'sprint-issues', 'sprintId' => 42])->get()
    )->not->toBeNull();

    $this->postJson("/api/{$account->id}/jira/sprints/42/issues", ['issue_keys' => ['PROJ-1', 'PROJ-2']], responderHeaders($token))
        ->assertNoContent();

    expect(
        TaggedCache::for(tags: ['jira', "jira:{$account->id}", "jira:{$account->id}:sprint:42"], context: ['type' => 'sprint-issues', 'sprintId' => 42])->get()
    )->toBeNull();
});
